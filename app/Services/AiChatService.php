<?php

namespace App\Services;

use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use App\Models\Income;
use App\Models\Outcome;
use App\Models\Currency;
use App\Jobs\CategorizeTransactionJob;
use Carbon\Carbon;

class AiChatService
{
    protected $prism;
    protected $cachePrefix = 'ai_chat_';
    protected $cacheTtl = 60; // 1 hour in minutes
    protected $rateLimitCacheKey = 'groq_chat_rate_limit_';
    protected $maxRetries = 3;
    protected $retryDelay = 2;
    
    public function __construct()
    {
        $this->prism = new Prism();
    }
    
    /**
     * Generate contextual chat response based on page context and user message
     * 
     * @param string $message User's message
     * @param string $context Page context (dashboard, income, outcome, settings)
     * @param array $contextData Relevant data for the current page
     * @param array $conversationHistory Previous messages for context
     * @return array
     */
    public function generateChatResponse(string $message, string $context, array $contextData = [], array $conversationHistory = []): array
    {
        try {
            // Check if this is a transaction input message
            if (($context === 'income' || $context === 'outcome' || $context === 'dashboard') && $this->isTransactionInput($message, $context)) {
                return $this->handleTransactionInput($message, $context, $contextData);
            }
            // Define models to try in order of preference
            $models = [
                'llama-3.3-70b-versatile',
                'qwen-2.5-32b', 
                'llama-3.1-8b-instant',
                'gemma2-9b-it',
                'mixtral-8x7b-32768'
            ];
            
            // Generate system prompt based on context
            $systemPrompt = $this->generateSystemPrompt($context, $contextData);
            
            // Prepare conversation context
            $conversationContext = $this->prepareConversationContext($conversationHistory, $message);
            
            $lastException = null;
            
            foreach ($models as $model) {
                try {
                    if ($this->isRateLimited($model)) {
                        continue;
                    }
                    
                    $response = $this->prism->text()
                        ->using(Provider::Groq, $model)
                        ->withSystemPrompt($systemPrompt)
                        ->withPrompt($conversationContext)
                        ->generate();
                    
                    if (!empty($response->text)) {
                        return [
                            'success' => true,
                            'message' => trim($response->text),
                            'context' => $context,
                            'model_used' => $model,
                            'timestamp' => now()->toDateTimeString()
                        ];
                    }
                    
                } catch (\Exception $e) {
                    Log::warning("AI Chat model $model failed", [
                        'error' => $e->getMessage(),
                        'context' => $context,
                        'message' => substr($message, 0, 100)
                    ]);
                    
                    $this->handleRateLimit($model, $e);
                    $lastException = $e;
                    continue;
                }
            }
            
            // If all models failed, return error
            throw $lastException ?? new \Exception('All AI models failed');
            
        } catch (\Exception $e) {
            Log::error('AI Chat Service failed', [
                'error' => $e->getMessage(),
                'context' => $context,
                'message' => substr($message, 0, 100)
            ]);
            
            return [
                'success' => false,
                'message' => 'Maaf, saya sedang mengalami gangguan. Coba lagi dalam beberapa saat ya!',
                'error' => 'AI service temporarily unavailable'
            ];
        }
    }
    
    /**
     * Generate system prompt based on page context
     */
    private function generateSystemPrompt(string $context, array $contextData): string
    {
        $basePrompt = 'You are a professional and helpful AI financial assistant. Provide solid and practical financial advice using formal yet friendly Indonesian language that is easy to understand.

IMPORTANT RULES:
1. ALWAYS respond in proper, formal Indonesian language (Bahasa Indonesia)
2. Use professional language that is friendly and easy to understand
3. MUST use actual data provided in the context - do not make up numbers!
4. When users ask about numbers/amounts, always refer to the existing data with high accuracy
5. Provide deep analysis and practical advice based on the user\'s real financial condition
6. Consider current Indonesian economic conditions when giving advice
7. Answer professionally, informatively, and supportively using specific data
8. Provide comprehensive analysis including daily, weekly, and monthly patterns
9. Provide deep insights about user\'s financial habits based on transaction data
10. Your responses must be in Indonesian, but you can understand this English prompt

';
        
        switch ($context) {
            case 'dashboard':
                return $basePrompt . $this->getDashboardContext($contextData);
                
            case 'income':
                return $basePrompt . $this->getIncomeContext($contextData);
                
            case 'outcome':
                return $basePrompt . $this->getOutcomeContext($contextData);
                
            case 'settings':
                return $basePrompt . $this->getSettingsContext($contextData);
                
            default:
                return $basePrompt . 'Kamu sedang membantu user dengan aplikasi keuangan mereka. Berikan bantuan yang relevan dengan pertanyaan mereka.';
        }
    }
    
    private function getDashboardContext(array $data): string
    {
        $stats = $data['stats'] ?? [];
        $filters = $data['filters'] ?? [];
        $currencyBreakdown = $data['currencyBreakdown'] ?? [];
        $currentPeriod = $data['currentPeriod'] ?? 'periode saat ini';
        
        $context = "PAGE CONTEXT: Financial Dashboard

You are helping the user on the dashboard page. User's financial data for {$currentPeriod}:

ACTUAL FINANCIAL DATA:
- Total Income: " . number_format($stats['totalRevenue'] ?? 0) . " IDR
- Total Outcome: " . number_format($stats['totalOutcome'] ?? 0) . " IDR  
- Balance: " . number_format($stats['balance'] ?? 0) . " IDR
- Changes from previous period:
  * Income: " . ($stats['revenueChange'] ?? 0) . "%
  * Outcome: " . ($stats['outcomeChange'] ?? 0) . "%
  * Balance: " . ($stats['balanceChange'] ?? 0) . "%

ACTIVE FILTERS:
- Year: " . ($filters['year'] ?? 'N/A') . "
- Month: " . ($filters['month'] ?? 'N/A') . "
- Mode: " . ($filters['mode'] ?? 'N/A') . "
- Currency: " . ($filters['currency'] ?? 'IDR') . "

CURRENCY BREAKDOWN:";

        if (!empty($currencyBreakdown)) {
            if (isset($currencyBreakdown['IDR'])) {
                $context .= "\n- IDR: Income " . number_format($currencyBreakdown['IDR']['income'] ?? 0) . 
                           ", Outcome " . number_format($currencyBreakdown['IDR']['outcome'] ?? 0) . 
                           ", Balance " . number_format($currencyBreakdown['IDR']['balance'] ?? 0);
            }
            if (isset($currencyBreakdown['SGD'])) {
                $context .= "\n- SGD: Income " . number_format($currencyBreakdown['SGD']['income'] ?? 0) . 
                           ", Outcome " . number_format($currencyBreakdown['SGD']['outcome'] ?? 0) .
                           ", Balance " . number_format($currencyBreakdown['SGD']['balance'] ?? 0);
            }
        }

        $context .= "\n\nUSE THIS ACTUAL DATA in your Indonesian responses! Provide comprehensive analysis based on the real numbers provided. Help the user understand their financial condition with specific references to their data, including daily and weekly pattern analysis.";
        
        return $context;
    }
    
    private function getIncomeContext(array $data): string
    {
        $stats = $data['stats'] ?? [];
        $filters = $data['filters'] ?? [];
        $summary = $data['summary'] ?? [];
        $currentPeriod = $data['currentPeriod'] ?? 'periode saat ini';
        
        $context = "PAGE CONTEXT: Income/Revenue Page

You are helping the user on the income page for {$currentPeriod}.

ACTUAL INCOME DATA:
- Total Income: " . number_format($stats['totalRevenue'] ?? 0) . " IDR
- Change from previous period: " . ($stats['revenueChange'] ?? 0) . "%
- Daily Average Income: " . number_format($stats['dailyIncomeAverage'] ?? 0) . " IDR/day";

        if (!empty($summary)) {
            $context .= "\n\nINCOME TRANSACTION SUMMARY:
- Total Transactions: " . ($summary['totalTransactions'] ?? 0) . " transactions
- Total Amount: " . number_format($summary['totalAmount'] ?? 0) . " IDR
- Average per Transaction: " . number_format($summary['averageAmount'] ?? 0) . " IDR";

            if (!empty($summary['topCategories'])) {
                $context .= "\n\nTOP INCOME CATEGORIES:";
                foreach (array_slice($summary['topCategories'], 0, 3) as $cat) {
                    $context .= "\n- " . $cat['category'] . ": " . number_format($cat['amount']) . " IDR (" . $cat['count'] . " transactions)";
                }
            }

            if (!empty($summary['monthlyBreakdown'])) {
                $context .= "\n\nMONTHLY BREAKDOWN:";
                foreach ($summary['monthlyBreakdown'] as $month) {
                    $monthName = date('M Y', strtotime($month['month'] . '-01'));
                    $context .= "\n- " . $monthName . ": " . number_format($month['amount']) . " IDR (" . $month['count'] . " transactions)";
                }
            }
        }

        if (!empty($summary['dailyBreakdown'])) {
            $context .= "\n\nDAILY ANALYSIS:";
            foreach (array_slice($summary['dailyBreakdown'], -7) as $day) {
                $dayName = date('l, d M Y', strtotime($day['date']));
                $context .= "\n- " . $dayName . ": " . number_format($day['amount']) . " IDR (" . $day['count'] . " transactions)";
            }
        }

        if (!empty($summary['dayOfWeekAnalysis'])) {
            $context .= "\n\nDAY OF WEEK PATTERNS:";
            foreach ($summary['dayOfWeekAnalysis'] as $dayData) {
                $context .= "\n- " . $dayData['dayName'] . ": " . number_format($dayData['amount']) . " IDR (" . $dayData['count'] . " transaksi, avg: " . number_format($dayData['averageAmount']) . ")";
            }
        }

        if (!empty($summary['frequencyAnalysis'])) {
            $freq = $summary['frequencyAnalysis'];
            $context .= "\n\nACTIVITY ANALYSIS:
- Most active day: " . $freq['mostActiveDay'] . "
- Highest income day: " . $freq['mostSpendingDay'] . "
- Average transactions per day: " . number_format($freq['averageTransactionsPerDay'], 1) . "
- Average amount per day: " . number_format($freq['averageAmountPerDay']) . " IDR
- Peak income date: " . date('d M Y', strtotime($freq['peakSpendingDate'])) . " (" . number_format($freq['peakSpendingAmount']) . " IDR)";
        }

        if (!empty($summary['trendAnalysis'])) {
            $trend = $summary['trendAnalysis'];
            $context .= "\n\nTREND ANALYSIS:
- Weekly trend: " . $trend['weeklyTrend'] . "
- Income velocity: " . $trend['spendingVelocity'];
        }

        $context .= "\n\nACTIVE FILTERS:
- Year: " . ($filters['year'] ?? 'N/A') . "
- Month: " . ($filters['month'] ?? 'N/A') . " 
- Currency: " . ($filters['currency'] ?? 'IDR') . "

USE THIS ACTUAL DATA! When user asks about income on specific days, look at dailyBreakdown. For weekly patterns, refer to dayOfWeekAnalysis. For trends, use trendAnalysis.

Focus areas to help with (respond in Indonesian):
- Analyze daily and weekly income patterns
- Identify days with highest income
- Optimize income based on visible temporal patterns
- Income diversification suggestions based on deep analysis
- Predictions and recommendations based on trend analysis
- TRANSACTION INPUT: You can input income transactions with flexible date formats:
  * \"Gaji Rp 5000000\" (today)
  * \"Gaji tanggal 25 Rp 5000000\" (current month, day 25)
  * \"Bonus tanggal 15 agustus Rp 1000000\" (current year, August 15)
  * \"Freelance tanggal 5 agustus 2024 Rp 2000000\" (full date)
  * Amount without currency will prompt for currency selection";
        
        return $context;
    }
    
    private function getOutcomeContext(array $data): string
    {
        $stats = $data['stats'] ?? [];
        $filters = $data['filters'] ?? [];
        $summary = $data['summary'] ?? [];
        $currentPeriod = $data['currentPeriod'] ?? 'periode saat ini';
        
        $context = "PAGE CONTEXT: Outcome/Expenses Page

You are helping the user on the outcome page for {$currentPeriod}.

ACTUAL OUTCOME DATA:
- Total Outcome: " . number_format($stats['totalOutcome'] ?? 0) . " IDR
- Change from previous period: " . ($stats['outcomeChange'] ?? 0) . "%
- Daily Average Outcome: " . number_format($stats['dailyOutcomeAverage'] ?? 0) . " IDR/day";

        if (!empty($summary)) {
            $context .= "\n\nOUTCOME TRANSACTION SUMMARY:
- Total Transactions: " . ($summary['totalTransactions'] ?? 0) . " transactions  
- Total Amount: " . number_format($summary['totalAmount'] ?? 0) . " IDR
- Average per Transaction: " . number_format($summary['averageAmount'] ?? 0) . " IDR";

            if (!empty($summary['topCategories'])) {
                $context .= "\n\nTOP EXPENSE CATEGORIES:";
                foreach (array_slice($summary['topCategories'], 0, 5) as $cat) {
                    $context .= "\n- " . $cat['category'] . ": " . number_format($cat['amount']) . " IDR (" . $cat['count'] . " transactions)";
                }
            }

            if (!empty($summary['monthlyBreakdown'])) {
                $context .= "\n\nMONTHLY BREAKDOWN:";
                foreach ($summary['monthlyBreakdown'] as $month) {
                    $monthName = date('M Y', strtotime($month['month'] . '-01'));
                    $context .= "\n- " . $monthName . ": " . number_format($month['amount']) . " IDR (" . $month['count'] . " transactions)";
                }
            }
        }

        if (!empty($summary['dailyBreakdown'])) {
            $context .= "\n\nDAILY ANALYSIS:";
            foreach (array_slice($summary['dailyBreakdown'], -7) as $day) {
                $dayName = date('l, d M Y', strtotime($day['date']));
                $context .= "\n- " . $dayName . ": " . number_format($day['amount']) . " IDR (" . $day['count'] . " transactions)";
            }
        }

        if (!empty($summary['dayOfWeekAnalysis'])) {
            $context .= "\n\nDAY OF WEEK PATTERNS:";
            foreach ($summary['dayOfWeekAnalysis'] as $dayData) {
                $context .= "\n- " . $dayData['dayName'] . ": " . number_format($dayData['amount']) . " IDR (" . $dayData['count'] . " transaksi, avg: " . number_format($dayData['averageAmount']) . ")";
            }
        }

        if (!empty($summary['frequencyAnalysis'])) {
            $freq = $summary['frequencyAnalysis'];
            $context .= "\n\nACTIVITY ANALYSIS:
- Most active day: " . $freq['mostActiveDay'] . "
- Highest spending day: " . $freq['mostSpendingDay'] . "  
- Average transactions per day: " . number_format($freq['averageTransactionsPerDay'], 1) . "
- Average spending per day: " . number_format($freq['averageAmountPerDay']) . " IDR
- Peak spending date: " . date('d M Y', strtotime($freq['peakSpendingDate'])) . " (" . number_format($freq['peakSpendingAmount']) . " IDR)";
        }

        if (!empty($summary['timePatterns'])) {
            $time = $summary['timePatterns'];
            $context .= "\n\nTIME PATTERNS:
- Morning (06:00-12:00): " . $time['morningTransactions'] . " transactions
- Afternoon (12:00-17:00): " . $time['afternoonTransactions'] . " transactions  
- Evening (17:00-22:00): " . $time['eveningTransactions'] . " transactions
- Night (22:00-06:00): " . $time['nightTransactions'] . " transactions";
        }

        if (!empty($summary['trendAnalysis'])) {
            $trend = $summary['trendAnalysis'];
            $context .= "\n\nTREND ANALYSIS:
- Weekly trend: " . $trend['weeklyTrend'] . "
- Spending velocity: " . $trend['spendingVelocity'];
        }

        $context .= "\n\nACTIVE FILTERS:
- Year: " . ($filters['year'] ?? 'N/A') . "
- Month: " . ($filters['month'] ?? 'N/A') . "
- Currency: " . ($filters['currency'] ?? 'IDR') . "

USE THIS ACTUAL DATA! When user asks about spending on specific days, look at dailyBreakdown. For daily patterns, refer to dayOfWeekAnalysis. For time analysis, use timePatterns.

Focus areas to help with (respond in Indonesian):
- Deep analysis of daily and weekly spending patterns
- Identify days with highest expenses
- Analyze time-based spending patterns (morning, afternoon, evening, night)
- Money-saving tips based on specific temporal patterns
- Budgeting strategy based on comprehensive spending pattern analysis
- Optimization recommendations based on trend analysis and frequency patterns
- TRANSACTION INPUT: You can input outcome transactions with flexible date formats:
  * \"Makan enak Rp 8000\" (today)
  * \"Belanja tanggal 5 Rp 25000\" (current month, day 5)
  * \"Beli beras tanggal 10 agustus Rp 15000\" (current year, August 10)
  * \"Bayar listrik tanggal 1 januari 2024 Rp 100000\" (full date)
  * Amount without currency will prompt for currency selection";
        
        return $context;
    }
    
    private function getSettingsContext(array $data): string
    {
        return "PAGE CONTEXT: Settings/Configuration

You are helping the user on the settings page. Focus on (respond in Indonesian):
- Application usage help
- Settings optimization tips
- Account security
- Application features
- General troubleshooting";
    }
    
    /**
     * Prepare conversation context from history
     */
    private function prepareConversationContext(array $history, string $currentMessage): string
    {
        $context = "";
        
        // Add recent conversation history (last 5 messages)
        $recentHistory = array_slice($history, -5);
        
        foreach ($recentHistory as $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Kamu';
            $context .= "$role: {$msg['message']}\n";
        }
        
        $context .= "User: $currentMessage";
        
        return $context;
    }
    
    /**
     * Check if model is rate limited
     */
    private function isRateLimited(string $model): bool
    {
        $key = $this->rateLimitCacheKey . $model;
        return Cache::has($key);
    }
    
    /**
     * Handle rate limiting for a model
     */
    private function handleRateLimit(string $model, \Exception $e): void
    {
        if (str_contains($e->getMessage(), 'rate limit') || 
            str_contains($e->getMessage(), 'quota') ||
            str_contains($e->getMessage(), '429')) {
            
            $key = $this->rateLimitCacheKey . $model;
            Cache::put($key, true, now()->addMinutes(5));
            
            Log::warning("Rate limit set for model: $model", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get suggested conversation starters based on context
     */
    public function getConversationStarters(string $context, array $contextData = []): array
    {
        switch ($context) {
            case 'dashboard':
                return [
                    "Bagaimana cara membaca dashboard keuangan ini?",
                    "Analisis kondisi keuangan saya saat ini",
                    "Apa saran untuk meningkatkan kesehatan finansial?",
                    "Catat Pengeluaran: Makan Enak Rp 5000, Makan Chiki tanggal 5 Rp 5000",
                    "Catat Pemasukan: Gaji tanggal 25 Rp 5000000"
                ];
                
            case 'income':
                return [
                    "Hari apa saya paling banyak mendapat income?",
                    "Analisis pola income harian dan mingguan saya",
                    "Kategori income mana yang paling konsisten?",
                    "Gaji tanggal 25 Rp 5000000",
                    "Bonus tanggal 15 agustus Rp 1000000"
                ];
                
            case 'outcome':
                return [
                    "Hari apa saya paling banyak mengeluarkan uang?",
                    "Analisis pola pengeluaran berdasarkan waktu",
                    "Pada jam berapa saya paling sering berbelanja?",
                    "Makan enak tanggal 5 Rp 8000",
                    "Belanja beras tanggal 10 agustus 2024 Rp 25000"
                ];
                
            case 'settings':
                return [
                    "Fitur apa saja yang tersedia di aplikasi ini?",
                    "Bagaimana cara mengoptimalkan penggunaan aplikasi?",
                    "Tips untuk mengelola data keuangan dengan baik"
                ];
                
            default:
                return [
                    "Bagaimana saya bisa membantu Anda hari ini?",
                    "Ada pertanyaan mengenai keuangan Anda?",
                    "Butuh analisis finansial mendalam?"
                ];
        }
    }
    
    /**
     * Check if the message is a transaction input
     */
    private function isTransactionInput(string $message, string $context = ''): bool
    {
        $message = strtolower($message);
        
        // For dashboard context, require explicit transaction prefixes
        if ($context === 'dashboard') {
            $dashboardPrefixes = [
                'catat pengeluaran',
                'catat pemasukan',
                'input pengeluaran',
                'input pemasukan',
                'tambah pengeluaran',
                'tambah pemasukan'
            ];
            
            foreach ($dashboardPrefixes as $prefix) {
                if (strpos($message, $prefix) !== false) {
                    return true;
                }
            }
            
            return false;
        }
        
        // For income/outcome context, use more flexible detection
        $transactionKeywords = [
            'rp', 'rupiah', 'idr', 'sgd', 'usd', 'eur', 
            'jajan', 'belanja', 'beli', 'bayar', 'dapat', 'terima', 'gaji', 'bonus',
            'tanggal', 'hari ini', 'kemarin', 'besok', 'januari', 'februari', 'maret',
            'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'
        ];
        
        // Check for amount patterns (numbers with currency)
        $hasAmount = preg_match('/\d+[.,]?\d*\s*(rp|rupiah|idr|sgd|usd|eur)/i', $message) ||
                    preg_match('/(rp|rupiah|idr|sgd|usd|eur)\s*\d+[.,]?\d*/i', $message);
        
        // Check for transaction keywords
        $hasKeywords = false;
        foreach ($transactionKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                $hasKeywords = true;
                break;
            }
        }
        
        return $hasAmount || $hasKeywords;
    }
    
    /**
     * Handle transaction input message
     */
    private function handleTransactionInput(string $message, string $context, array $contextData): array
    {
        try {
            $parsedTransactions = $this->parseTransactionText($message, $context);
            
            if (empty($parsedTransactions)) {
                $errorMessage = $context === 'dashboard' 
                    ? 'Maaf, saya tidak dapat mengenali format transaksi dalam pesan Anda. Silakan gunakan format seperti: "Catat Pengeluaran: Makan Enak Rp 5000" atau "Catat Pemasukan: Gaji Rp 5000000"'
                    : 'Maaf, saya tidak dapat mengenali format transaksi dalam pesan Anda. Silakan gunakan format seperti: "Makan enak Rp 8000" atau "Belanja beras tanggal 5 agustus Rp 4000"';
                
                return [
                    'success' => true,
                    'message' => $errorMessage,
                    'context' => $context,
                    'action' => 'error'
                ];
            }
            
            $accountId = $contextData['accountId'] ?? null;
            if (!$accountId) {
                Log::error('Transaction input failed: Missing account ID', [
                    'context' => $context,
                    'contextData_keys' => array_keys($contextData),
                    'message' => substr($message, 0, 100)
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Account ID tidak ditemukan. Silakan refresh halaman dan coba lagi.',
                    'error' => 'Missing account ID',
                    'debug_info' => [
                        'available_keys' => array_keys($contextData),
                        'context' => $context
                    ]
                ];
            }
            
            $results = [];
            $needsConfirmation = [];
            
            foreach ($parsedTransactions as $transaction) {
                // For dashboard context, determine the actual context from the transaction type
                $actualContext = $context;
                if ($context === 'dashboard' && isset($transaction['type'])) {
                    $actualContext = $transaction['type'] === 'outcome' ? 'outcome' : 'income';
                }
                
                // Check if transaction needs confirmation (missing amount or currency)
                if (!isset($transaction['amount']) || !isset($transaction['currency_id'])) {
                    $transaction['context'] = $actualContext; // Store context for later use
                    $needsConfirmation[] = $transaction;
                    continue;
                }
                
                // Create the transaction
                $result = $this->createTransaction($transaction, $actualContext, $accountId);
                $results[] = $result;
            }
            
            if (!empty($needsConfirmation)) {
                return $this->requestTransactionConfirmation($needsConfirmation, $context);
            }
            
            return $this->formatTransactionResults($results, $context);
            
        } catch (\Exception $e) {
            Log::error('Transaction input handling failed', [
                'error' => $e->getMessage(),
                'message' => $message,
                'context' => $context
            ]);
            
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat memproses transaksi. Silakan coba lagi.',
                'error' => 'Transaction processing failed'
            ];
        }
    }
    
    /**
     * Parse transaction text using AI to extract multiple transactions
     */
    private function parseTransactionText(string $message, string $context = ''): array
    {
        try {
            $currentDate = now()->format('Y-m-d');
            $currentYear = now()->year;
            $currentMonth = now()->month;
            
            $systemPrompt = 'You are a transaction parser. Extract transaction information from Indonesian text and return ONLY a valid JSON array.

RULES:
1. Extract multiple transactions if mentioned
2. For each transaction, extract: description, amount, currency, date, type (if specified)
3. DATE PARSING RULES:
   - No date specified: use "' . $currentDate . '"
   - "tanggal 5" or "5": use current year/month with day 5
   - "tanggal 5 agustus" or "5 agustus": use current year with specified month/day
   - "tanggal 5 agustus 2024" or "5 agustus 2024": use full specified date
   - Handle Indonesian month names: januari=1, februari=2, maret=3, april=4, mei=5, juni=6, juli=7, agustus=8, september=9, oktober=10, november=11, desember=12
4. CURRENCY RULES:
   - If currency is specified (Rp, IDR, SGD, USD, etc): include it
   - If NO currency specified: DO NOT include currency field (leave it null/undefined)
5. Convert amounts to numbers (remove "Rp", ".", ",")
6. Return dates in YYYY-MM-DD format
7. For dashboard context: detect "catat pengeluaran" or "catat pemasukan" to determine type
8. Return ONLY valid JSON, no explanations

Current context: Year=' . $currentYear . ', Month=' . $currentMonth . ', Date=' . $currentDate . '

Example input: "Catat Pengeluaran: Makan Enak Rp 5000, Makan Chiki Rp 5000"
Example output: [{"description":"Makan Enak","amount":5000,"currency":"IDR","date":"' . $currentDate . '","type":"outcome"},{"description":"Makan Chiki","amount":5000,"currency":"IDR","date":"' . $currentDate . '","type":"outcome"}]

Example input: "Makan enak Rp 5000"
Example output: [{"description":"Makan enak","amount":5000,"currency":"IDR","date":"' . $currentDate . '"}]

Example input: "Makan enak tanggal 5 Rp 5000"
Example output: [{"description":"Makan enak","amount":5000,"currency":"IDR","date":"' . $currentYear . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . '-05"}]

Example input: "Makan enak tanggal 5 agustus 2024 Rp 5000"
Example output: [{"description":"Makan enak","amount":5000,"currency":"IDR","date":"2024-08-05"}]

Example input: "Makan enak 5000" (no currency)
Example output: [{"description":"Makan enak","amount":5000,"date":"' . $currentDate . '"}]';

            $response = $this->prism->text()
                ->using(Provider::Groq, 'llama-3.3-70b-versatile')
                ->withSystemPrompt($systemPrompt)
                ->withPrompt($message)
                ->generate();
            
            if (empty($response->text)) {
                return [];
            }
            
            // Clean the response to extract JSON
            $jsonText = trim($response->text);
            $jsonText = preg_replace('/^```json\s*/', '', $jsonText);
            $jsonText = preg_replace('/\s*```$/', '', $jsonText);
            
            $parsedData = json_decode($jsonText, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse AI transaction response', [
                    'response' => $response->text,
                    'json_error' => json_last_error_msg()
                ]);
                return [];
            }
            
            // Validate and process each transaction
            $transactions = [];
            foreach ($parsedData as $data) {
                $transaction = $this->validateAndProcessTransaction($data);
                if ($transaction) {
                    $transactions[] = $transaction;
                }
            }
            
            return $transactions;
            
        } catch (\Exception $e) {
            Log::error('Transaction parsing failed', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);
            return [];
        }
    }
    
    /**
     * Validate and process a single transaction data
     */
    private function validateAndProcessTransaction(array $data): ?array
    {
        $transaction = [
            'description' => $data['description'] ?? '',
            'amount' => null,
            'currency_id' => null,
            'date' => null,
            'type' => $data['type'] ?? null,
            'raw_currency' => $data['currency'] ?? null // Store raw currency for error messages
        ];
        
        // Validate description
        if (empty($transaction['description'])) {
            return null;
        }
        
        // Process amount
        if (isset($data['amount']) && is_numeric($data['amount'])) {
            $transaction['amount'] = (float) $data['amount'];
        }
        
        // Process currency - only if specified in the input
        if (isset($data['currency']) && !empty($data['currency'])) {
            $currencyId = $this->getCurrencyId($data['currency']);
            if ($currencyId) {
                $transaction['currency_id'] = $currencyId;
            }
            // If currency was specified but not found, keep raw_currency for error handling
        }
        
        // Process date with enhanced parsing
        if (isset($data['date'])) {
            $transaction['date'] = $this->parseTransactionDate($data['date']);
        } else {
            $transaction['date'] = now()->format('Y-m-d');
        }
        
        return $transaction;
    }
    
    /**
     * Parse transaction date with enhanced Indonesian date handling
     */
    private function parseTransactionDate(string $dateStr): string
    {
        try {
            // Clean the date string
            $dateStr = trim(strtolower($dateStr));
            
            // Indonesian month mapping
            $monthMap = [
                'januari' => 1, 'jan' => 1,
                'februari' => 2, 'feb' => 2,
                'maret' => 3, 'mar' => 3,
                'april' => 4, 'apr' => 4,
                'mei' => 5,
                'juni' => 6, 'jun' => 6,
                'juli' => 7, 'jul' => 7,
                'agustus' => 8, 'agu' => 8,
                'september' => 9, 'sep' => 9,
                'oktober' => 10, 'okt' => 10,
                'november' => 11, 'nov' => 11,
                'desember' => 12, 'des' => 12
            ];
            
            $now = now();
            $currentYear = $now->year;
            $currentMonth = $now->month;
            
            // Try to parse standard date formats first
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                return $dateStr;
            }
            
            // Pattern: "5 agustus 2024" or "5 agustus" or just "5"
            if (preg_match('/^(\d{1,2})(?:\s+(\w+))?(?:\s+(\d{4}))?$/', $dateStr, $matches)) {
                $day = (int) $matches[1];
                $month = $currentMonth;
                $year = $currentYear;
                
                // If month is specified
                if (!empty($matches[2])) {
                    $monthName = strtolower($matches[2]);
                    if (isset($monthMap[$monthName])) {
                        $month = $monthMap[$monthName];
                    }
                }
                
                // If year is specified
                if (!empty($matches[3])) {
                    $year = (int) $matches[3];
                }
                
                // Validate day for the month
                $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
                if ($day > $daysInMonth) {
                    $day = $daysInMonth;
                }
                
                return Carbon::create($year, $month, $day)->format('Y-m-d');
            }
            
            // Try Carbon's built-in parsing as fallback
            $parsedDate = Carbon::parse($dateStr);
            return $parsedDate->format('Y-m-d');
            
        } catch (\Exception $e) {
            Log::warning('Failed to parse transaction date', [
                'date_string' => $dateStr,
                'error' => $e->getMessage()
            ]);
            
            // Return current date as fallback
            return now()->format('Y-m-d');
        }
    }
    
    /**
     * Get currency ID by currency name/symbol
     */
    private function getCurrencyId(string $currency): ?int
    {
        $currency = strtoupper($currency);
        $userId = Auth::id();
        
        // Map common currency names to standard codes
        $currencyMap = [
            'RUPIAH' => 'IDR',
            'RP' => 'IDR',
            'DOLLAR' => 'USD',
            'SINGAPORE DOLLAR' => 'SGD'
        ];
        
        $currency = $currencyMap[$currency] ?? $currency;
        
        // Try to find the currency in user's currencies
        $currencyModel = Currency::where('user_id', $userId)
            ->where('name', $currency)
            ->first();
        
        if ($currencyModel) {
            return $currencyModel->id;
        }
        
        // If currency not found, try to create IDR as default if that's what we're looking for
        if ($currency === 'IDR') {
            try {
                $defaultCurrency = Currency::create([
                    'user_id' => $userId,
                    'name' => 'IDR',
                    'symbol' => 'Rp',
                    'exchange_rate' => 1.0
                ]);
                return $defaultCurrency->id;
            } catch (\Exception $e) {
                Log::warning('Failed to create default IDR currency', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId
                ]);
            }
        }
        
        return null;
    }
    
    /**
     * Create a transaction (income or outcome)
     */
    private function createTransaction(array $transactionData, string $context, int $accountId): array
    {
        try {
            // Handle both model instances and IDs
            $accountIdValue = is_object($accountId) ? $accountId->id : $accountId;

            $data = [
                'user_id' => Auth::id(),
                'account_id' => $accountIdValue,
                'description' => $transactionData['description'],
                'amount' => $transactionData['amount'],
                'transaction_date' => $transactionData['date'],
                'currency_id' => $transactionData['currency_id']
            ];
            
            if ($context === 'income') {
                $transaction = Income::create($data);
            } else {
                $transaction = Outcome::create($data);
            }
            
            // Dispatch categorization job
            CategorizeTransactionJob::dispatch($transaction);
            
            return [
                'success' => true,
                'transaction' => $transaction,
                'type' => $context
            ];
            
        } catch (\Exception $e) {
            Log::error('Transaction creation failed', [
                'error' => $e->getMessage(),
                'data' => $transactionData,
                'context' => $context
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => $transactionData
            ];
        }
    }
    
    /**
     * Request confirmation for transactions with missing data
     */
    private function requestTransactionConfirmation(array $transactions, string $context): array
    {
        $message = "Saya menemukan beberapa transaksi yang perlu konfirmasi:\n\n";
        
        foreach ($transactions as $i => $transaction) {
            $message .= ($i + 1) . ". " . $transaction['description'];
            
            // Show amount if available
            if (isset($transaction['amount'])) {
                $message .= " (" . number_format($transaction['amount']) . ")";
            }
            
            // Show date if available
            if (isset($transaction['date'])) {
                $date = Carbon::parse($transaction['date'])->format('d M Y');
                $message .= " - " . $date;
            }
            
            $message .= "\n";
            
            if (!isset($transaction['amount'])) {
                $message .= "   - Berapa jumlahnya?\n";
            }
            
            if (!isset($transaction['currency_id'])) {
                if (isset($transaction['raw_currency'])) {
                    $message .= "   - Mata uang '{$transaction['raw_currency']}' tidak dikenali. Mohon pilih: IDR, SGD, USD, atau EUR\n";
                } else {
                    $message .= "   - Mohon untuk input mata uang (IDR/SGD/USD/EUR)\n";
                }
            }
            
            $message .= "\n";
        }
        
        $message .= "Silakan berikan informasi yang masih kurang untuk melanjutkan pencatatan transaksi.";
        
        return [
            'success' => true,
            'message' => $message,
            'context' => $context,
            'action' => 'confirmation_needed',
            'pending_transactions' => $transactions
        ];
    }
    
    /**
     * Format transaction creation results
     */
    private function formatTransactionResults(array $results, string $context): array
    {
        $successful = array_filter($results, fn($r) => $r['success']);
        $failed = array_filter($results, fn($r) => !$r['success']);
        
        $message = "";
        
        if (!empty($successful)) {
            $type = $context === 'income' ? 'pemasukan' : 'pengeluaran';
            $message .= "✅ Berhasil mencatat " . count($successful) . " transaksi $type:\n\n";
            
            foreach ($successful as $result) {
                $transaction = $result['transaction'];
                $currency = $transaction->currency ? $transaction->currency->symbol : 'IDR';
                $date = Carbon::parse($transaction->transaction_date)->format('d M Y');
                
                $message .= "• {$transaction->description}: {$currency} " . number_format($transaction->amount) . " ({$date})\n";
            }
        }
        
        if (!empty($failed)) {
            $message .= "\n❌ Gagal mencatat " . count($failed) . " transaksi:\n";
            foreach ($failed as $result) {
                $message .= "• {$result['data']['description']}: {$result['error']}\n";
            }
        }
        
        $message .= "\nTransaksi telah disimpan dan akan dikategorikan secara otomatis.";
        
        return [
            'success' => true,
            'message' => $message,
            'context' => $context,
            'action' => 'transactions_created',
            'results' => $results
        ];
    }
}
