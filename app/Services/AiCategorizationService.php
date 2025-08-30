<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AiCategorizationService
{
    protected $prism;
    protected $cachePrefix = 'ai_categorization_';
    protected $cacheKeysKey = 'ai_categorization_keys';
    protected $cacheDescriptionsKey = 'ai_categorization_descriptions';
    protected $cacheTtl = 1440; // 24 hours in minutes
    protected $similarityThreshold = 0.8; // Threshold for similarity matching
    protected $batchSize = 10; // Maximum number of transactions to process in a batch
    protected $rateLimitCacheKey = 'groq_rate_limit_';
    protected $maxRetries = 3; // Maximum number of retries when hitting rate limits
    protected $retryDelay = 2; // Base delay in seconds before retrying
    
    public function __construct()
    {
        $this->prism = new Prism();
    }
    
    /**
     * Categorize a transaction based on its description
     *
     * @param Transaction|string $transaction Transaction object or description string
     * @return Category|null
     */
    public function categorizeTransaction($transaction)
    {
        // Get the description and user_id from the transaction
        $description = is_string($transaction) ? $transaction : $transaction->description;
        $userId = is_string($transaction) ? null : $transaction->user_id;
        
        // Check if this is a zakat-related transaction (do this before cache check)
        if ($this->isZakatTransaction($description)) {
            return $this->findOrCreateCategory('Zakat', $userId);
        }

        // Check if this is a THR-related transaction (holiday allowance)
        if ($this->isThrTransaction($description)) {
            return $this->findOrCreateCategory('Pendapatan', $userId);
        }

        // Special case for "tempe" to ensure it's always categorized correctly
        if (strtolower(trim($description)) === 'tempe' || preg_match('/\btempe\b/i', $description)) {
            return $this->findOrCreateCategory('Makanan', $userId);
        }
        
        // Normalize the description for better matching
        $normalizedDescription = $this->normalizeDescription($description);
        
        // Check if we have a cached category for this description
        $cacheKey = $this->generateCacheKey($normalizedDescription);
        if (Cache::has($cacheKey)) {
            $categoryName = Cache::get($cacheKey);
            if (!empty($categoryName)) {
                return $this->findOrCreateCategory($categoryName, $userId);
            }
        }

        // Check for similar descriptions in cache
        $similarCategory = $this->findSimilarCachedCategory($normalizedDescription, $userId);
        if ($similarCategory instanceof Category) {
            return $similarCategory;
        }
        
        // Define models to try in order of preference
        $models = [
            'llama-3.3-70b-versatile',  // Latest and most powerful Llama model
            'qwen-2.5-32b',             // Powerful Alibaba model with good multilingual capabilities
            'llama-3.1-8b-instant',     // Improved Llama 3 with larger context
            'gemma2-9b-it',             // Google's improved Gemma model
            'mixtral-8x7b-32768'        // Fallback to Mixtral
        ];
        
        // Get existing categories to provide as context
        $existingCategories = Category::all()->pluck('name')->toArray();
        
        // Prepare data for the AI model
        $data = [
            'description' => $normalizedDescription,
            'existing_categories' => $existingCategories
        ];
        
        // Try each model until one works
        $lastException = null;
        
        foreach ($models as $model) {
            try {
                // Check if we're rate limited for this model
                if ($this->isRateLimited($model)) {
                    Log::warning("Rate limit reached for model: $model, skipping", [
                        'description' => $normalizedDescription
                    ]);
                    continue; // Try the next model
                }
                
                // Generate category using the current model with Prism
                $response = $this->prism->text()
                    ->using(Provider::Groq, $model)
                    ->withSystemPrompt('You are a financial categorization assistant. Your task is to categorize financial transactions based on their descriptions. You must analyze the transaction description and assign it to the most appropriate category.

CRITICAL RULES:
1. Return ONLY the category name in Bahasa Indonesia, nothing else. No explanations, no descriptions.
2. Use simple and clear category names ONLY (examples: "Makanan", "Transportasi", "Hiburan", "Utilitas", "Belanja", "Jajan", etc.)
3. NEVER include explanations or justifications in your response.
4. NEVER return verbose descriptions as category names. Keep category names short and concise (1-2 words only).
5. NEVER begin your response with phrases like "Okay", "Based on the", "The transaction", etc.
6. Category names must be in Bahasa Indonesia and in singular form.
7. Be consistent with existing categories whenever possible.
8. If the transaction is related to zakat, categorize it as "Zakat".
9. If the transaction is related to charity, sedekah, amal, categorize it as "Sodaqoh".
10. If the transaction is related to snacks or jajan, categorize it as "Jajan" (not "Jajanan" or other variations).
11. NEVER use single food item names like "Tahu", "Tempe", "Bakso" as categories - use the general category "Makanan" instead.
12. NEVER use specific store or brand names as categories.
13. For cigarettes or tobacco products, always use "Rokok" category.
14. For fuel (bensin, pertamax, pertalite), use "Transportasi" category.
15. For telecommunication expenses (pulsa, paket data), use "Telekomunikasi" category.
16. IMPORTANT: "Tempe" is an Indonesian food item and should ALWAYS be categorized as "Makanan", not "Hiburan".
17. Do not confuse "Tempe" (Indonesian food) with "Temple" (place of worship).
18. Any income, payment received, or money coming in should ALWAYS be categorized as "Pendapatan".
19. Transactions containing "Bayar hutang", "Bayar utang", "Jual", "Penjualan" should ALWAYS be categorized as "Pendapatan".
20. IMPORTANT: Indonesian food items like Dawet, Soto, Bakso, Pecel, Gado-gado, Rendang, etc. should ALWAYS be categorized as "Makanan".
21. IMPORTANT: If a transaction contains both jajan/snack keywords AND income keywords, prioritize categorizing it as "Jajan" not "Pendapatan".
22. IMPORTANT: If a transaction contains the word "Hutang" or "Utang" (taking a loan, debt), categorize it as "Hutang".
23. IMPORTANT: BUT if the transaction is about PAYING OFF debt ("Bayar hutang", "Bayar utang", "Pelunasan hutang", etc.), categorize it as "Pendapatan".
24. IMPORTANT: "Uti" is a Javanese word meaning "mother" and should NOT be categorized as "Utilitas" (utilities) - use "Lain-lain" instead.

STANDARD CATEGORIES TO USE:
- "Makanan" - for all food and meal expenses
- "Jajan" - for snacks, gorengan, and small food items
- "Transportasi" - for all transportation costs, fuel, parking, maintenance
- "Hiburan" - for entertainment expenses
- "Utilitas" - for utilities like electricity, water, gas
- "Perumahan" - for housing and rent expenses
- "Belanja" - for shopping and goods purchases
- "Kesehatan" - for medical and health expenses
- "Pendidikan" - for education expenses
- "Perlengkapan Kantor" - for office supplies
- "Telekomunikasi" - for phone, internet and communication
- "Sembako" - for basic household necessities
- "Rokok" - for cigarettes and tobacco products
- "Investasi" - for investments
- "Tabungan" - for savings
- "Pendapatan" - for all income, salary, payments received, money coming in
- "Hutang" - for taking loans, debt, borrowing money
- "Lain-lain" - for anything that doesn\'t fit the above

EXAMPLES OF CORRECT RESPONSES:
- "Makan siang di McDonald\'s" → "Makanan"
- "Naik Uber ke bandara" → "Transportasi"
- "Langganan Netflix" → "Hiburan"
- "Tagihan listrik" → "Utilitas"
- "Sewa apartemen" → "Perumahan"
- "Sepatu baru dari Nike" → "Belanja"
- "beliin mbak cindy rice cooker" → "Belanja"
- "Beli ayam betutu" → "Makanan"
- "Print dokumen" → "Perlengkapan Kantor"
- "Beli beras dan sayur" → "Sembako"
- "Beli buku" → "Pendidikan"
- "Bayar SPP sekolah" → "Pendidikan"
- "Beli obat di apotek" → "Kesehatan"
- "Konsultasi dokter" → "Kesehatan"
- "Bayar cicilan motor" → "Cicilan"
- "Transfer ke tabungan" → "Tabungan"
- "Gaji bulanan" → "Pendapatan"
- "Bonus tahunan" → "Pendapatan"
- "Honor freelance" → "Pendapatan"
- "Dana masuk dari client" → "Pendapatan"
- "Bayaran jasa desain" → "Pendapatan"
- "Pemasukan dari YouTube" → "Pendapatan"
- "Uang dari penjualan barang" → "Pendapatan"
- "Cashback belanja online" → "Pendapatan"
- "Refund tiket pesawat" → "Pendapatan"
- "Bayar hutang dari Budi" → "Pendapatan"
- "Bayar utang cicilan" → "Pendapatan"
- "Jual motor bekas" → "Pendapatan"
- "Penjualan barang online" → "Pendapatan"
- "Donasi untuk bencana" → "Amal"
- "Bayar zakat" → "Zakat"
- "Zakat penghasilan" → "Zakat"
- "Zakat fitrah" → "Zakat"
- "Zakat maal" → "Zakat"
- "Sedekah mingguan" → "Sodaqoh"
- "Beli pulsa" → "Telekomunikasi"
- "Bayar internet" → "Telekomunikasi"
- "Jajan di warung" → "Jajan"
- "Beli gorengan" → "Jajan"
- "Tempe" → "Makanan"
- "Tahu" → "Makanan"
- "Beli tempe goreng" → "Makanan"
- "Temple" → "Hiburan"
- "Bensin Pertamax" → "Transportasi"
- "Rokok Marlboro" → "Rokok"
- "Gudang Garam" → "Rokok"
- "Isi pulsa Telkomsel" → "Telekomunikasi"
- "Paket data XL" → "Telekomunikasi"
- "Parkir motor" → "Transportasi"
- "Servis mobil" → "Servis"
- "SPBU" → "Bensin"
- "Soto ayam" → "Makanan"
- "Dawet Ireng" → "Makanan"
- "Pecel Lele" → "Makanan"
- "Es dawet" → "Makanan"
- "Jajan honor" → "Jajan"
- "Camilan dana" → "Jajan"
- "Gorengan pemasukan" → "Jajan"
- "Hutang ke Budi" → "Hutang"
- "Pinjam uang" → "Hutang"
- "Ambil kredit motor" → "Hutang"
- "Bayar hutang ke Budi" → "Pendapatan"
- "Lunasi pinjaman" → "Pendapatan"
- "Uti" → "Lain-lain"
- "Kirim ke Uti" → "Lain-lain"')
                    ->withPrompt($normalizedDescription)  // Just send the description directly
                    ->usingTemperature(0.2) // Lower temperature for more consistent output
                    ->withMaxTokens(50)
                    ->generate();
                
                // Update rate limit information from response headers
                $this->updateRateLimitInfo($model, $response);
                
                // Log the response for debugging
                Log::debug('Prism API Response for Categorization', [
                    'model' => $model,
                    'description' => $normalizedDescription,
                    'response' => $response,
                    'response_type' => gettype($response),
                    'response_class' => is_object($response) ? get_class($response) : null
                ]);
                
                // Extract the category name from the response
                $categoryName = '';
                if (is_object($response)) {
                    if (method_exists($response, '__toString')) {
                        $categoryName = (string) $response;
                    } elseif (isset($response->text)) {
                        $categoryName = $response->text;
                    } elseif (property_exists($response, 'content')) {
                        $categoryName = $response->content ?? '';
                    }
                } elseif (is_string($response)) {
                    $categoryName = $response;
                } elseif (is_array($response)) {
                    if (isset($response['text'])) {
                        $categoryName = $response['text'];
                    } elseif (isset($response['content'])) {
                        $categoryName = $response['content'];
                    }
                }

                Log::debug('Extracted category name', [
                    'raw_category_name' => $categoryName,
                    'description' => $normalizedDescription
                ]);
                
                // Clean up the category name
                $categoryName = trim($categoryName);
                $categoryName = trim($categoryName, '"\'');
                $categoryName = ucfirst($categoryName);
                
                // Additional cleanup to handle verbose responses
                if (Str::contains($categoryName, ['Okay', 'Based on', 'The transaction', 'This transaction', 'I would'])) {
                    // If we detect explanatory text, extract just the category or default to Lain-lain
                    preg_match('/["\'](.*?)["\']/', $categoryName, $matches);
                    if (!empty($matches[1])) {
                        $categoryName = $matches[1];
                    } else {
                        $categoryName = 'Lain-lain';
                    }
                }
                
                // If category is too long, it's probably a description not a category
                if (Str::length($categoryName) > 30) {
                    $categoryName = 'Lain-lain';
                }
                
                // Handle "Jajan" variations
                if (in_array(strtolower($categoryName), ['jajanan', 'jajan-jajan', 'jajan jajan'])) {
                    $categoryName = 'Jajan';
                }
                
                // Handle single-word categories by mapping them to standard categories
                $categoryName = $this->mapSingleWordCategory($categoryName);
                
                Log::debug('Cleaned category name', [
                    'cleaned_category_name' => $categoryName,
                    'description' => $normalizedDescription
                ]);
                
                if (empty($categoryName)) {
                    throw new \Exception('Empty category name received from AI');
                }
                
                // Cache the result and track the cache key
                if (!empty($categoryName)) {
                    Cache::put($cacheKey, $categoryName, $this->cacheTtl * 60);
                    
                    // Track this cache key
                    $cacheKeys = Cache::get($this->cacheKeysKey, []);
                    if (!in_array($cacheKey, $cacheKeys)) {
                        $cacheKeys[] = $cacheKey;
                        Cache::put($this->cacheKeysKey, $cacheKeys, $this->cacheTtl * 60);
                    }
                }
                
                // Find or create the category
                return $this->findOrCreateCategory($categoryName, $userId);
            } catch (\Exception $e) {
                $lastException = $e;
                
                // Check if this is a rate limit error (429)
                if ($this->isRateLimitException($e)) {
                    // Mark this model as rate limited
                    $this->markRateLimited($model, $e);
                    
                    Log::warning("Rate limit hit for model: $model", [
                        'error' => $e->getMessage(),
                        'description' => $normalizedDescription
                    ]);
                } else {
                    Log::warning("Failed to categorize transaction with model: $model", [
                        'error' => $e->getMessage(),
                        'description' => $normalizedDescription
                    ]);
                }
                
                // Continue to the next model
                continue;
            }
        }
        
        // If all models fail, log the error and return Lain-lain category
        Log::error('All AI models failed to categorize transaction', [
            'last_exception' => $lastException ? $lastException->getMessage() : 'Unknown error',
            'description' => $normalizedDescription
        ]);
        
        // Return Lain-lain category instead of null
        return $this->findOrCreateCategory('Lain-lain', $userId);
    }
    
    /**
     * Find or create a category by name
     *
     * @param string $name
     * @param int|null $userId
     * @return Category
     */
    public function findOrCreateCategory($name, $userId = null)
    {
        if (empty($name)) {
            return null;
        }
        
        // Normalize the category name
        $name = ucfirst(trim($name));
        
        // Check if the category is an unclear or unknown category
        $unclearCategories = [
            'Tidak dapat dikategorikan',
            'Transaksi tidak jelas',
            'Unknown',
            'Transaksi tidak diketahui',
            'Tidak diketahui',
            'Tidak terkategorikan',
            'Uncategorized',
            'Tidak dapat ditentukan',
            'Tidak dapat diidentifikasi',
            'Tidak dapat diklasifikasikan',
            'Lainnya',
            'Other',
            'Miscellaneous',
            'General',
            'Unclassified',
            'Unidentified',
            'Undefined'
        ];
        
        // If the category is in the list of unclear categories, use "Lain-lain" instead
        if (in_array($name, $unclearCategories) || 
            stripos($name, 'tidak') !== false || 
            stripos($name, 'unknown') !== false || 
            stripos($name, 'uncategorized') !== false ||
            stripos($name, 'unclassified') !== false ||
            stripos($name, 'other') !== false ||
            stripos($name, 'misc') !== false ||
            stripos($name, 'general') !== false ||
            stripos($name, 'lain') !== false ||
            preg_match('/^[?\.]+$/', $name) ||
            strlen($name) < 3 ||  // Very short names
            preg_match('/^[^a-zA-Z]+$/', $name) || // Only numbers or special characters
            preg_match('/^(undefined|null|nan|n\/a)$/i', $name)) { // Common programming "empty" values
            
            Log::info('Detected unclear category', [
                'original_category' => $name,
                'replaced_with' => 'Lain-lain'
            ]);
            
            $name = 'Lain-lain';
        }

        // Map categories to appropriate icons (used as fallback)
        $iconMap = [
            'Makanan' => 'UtensilsIcon',
            'Transportasi' => 'CarIcon',
            'Hiburan' => 'GamepadIcon',
            'Utilitas' => 'BoltIcon',
            'Perumahan' => 'HomeIcon',
            'Belanja' => 'ShoppingBagIcon',
            'Kesehatan' => 'HeartIcon',
            'Pendidikan' => 'GraduationCapIcon',
            'Perlengkapan Kantor' => 'BriefcaseIcon',
            'Sembako' => 'PackageIcon',
            'Investasi' => 'TrendingUpIcon',
            'Asuransi' => 'ShieldIcon',
            'Amal' => 'GiftIcon',
            'Perawatan Pribadi' => 'SparklesIcon',
            'Lain-lain' => 'CircleDollarSignIcon',
            'Zakat' => 'GiftIcon',
            'Sodaqoh' => 'GiftIcon',
            'Telekomunikasi' => 'PhoneIcon',
            'Cicilan' => 'CreditCardIcon',
            'Tabungan' => 'PiggyBankIcon',
            'Pendapatan' => 'DollarSignIcon',
        ];
        
        // Try to generate an icon based on the category name using AI
        // If that fails, use the predefined mapping or a default icon
        try {
            $icon = $this->generateIconForCategory($name);
            Log::info('Generated icon for category', [
                'category' => $name,
                'icon' => $icon
            ]);
        } catch (\Exception $e) {
            // If AI icon generation fails, use the predefined mapping or default
            $icon = $iconMap[$name] ?? 'CircleDollarSignIcon';
            Log::warning('Failed to generate icon using AI, using fallback', [
                'category' => $name,
                'fallback_icon' => $icon,
                'error' => $e->getMessage()
            ]);
        }
        
        $attributes = ['name' => $name];
        $values = ['description' => 'Auto-generated category', 'icon' => $icon];

        // Add user_id to the query if provided
        if ($userId !== null) {
            $attributes['user_id'] = $userId;
            $values['user_id'] = $userId;
        }

        return Category::firstOrCreate($attributes, $values);
    }
    
    /**
     * Generate an appropriate icon for a category based on its name
     * 
     * @param string $categoryName
     * @return string The name of the icon component to use
     */
    private function generateIconForCategory($categoryName)
    {
        $categoryName = strtolower($categoryName);
        
        // Map category keywords to icons
        $iconMappings = [
            // Food and dining
            'makan' => 'UtensilsIcon',
            'makanan' => 'UtensilsIcon',
            'kuliner' => 'UtensilsIcon',
            'restoran' => 'UtensilsIcon',
            'restaurant' => 'UtensilsIcon',
            'cafe' => 'CoffeeIcon',
            'kopi' => 'CoffeeIcon',
            'coffee' => 'CoffeeIcon',
            'jajan' => 'CandyIcon',
            'snack' => 'CandyIcon',
            'cemilan' => 'CandyIcon',
            'camilan' => 'CandyIcon',
            'gorengan' => 'CandyIcon',
            
            // Transportation
            'transportasi' => 'CarIcon',
            'transport' => 'CarIcon',
            'bensin' => 'CarIcon',
            'bbm' => 'CarIcon',
            'pertamax' => 'CarIcon',
            'pertalite' => 'CarIcon',
            'parkir' => 'CarIcon',
            'parking' => 'CarIcon',
            'mobil' => 'CarIcon',
            'car' => 'CarIcon',
            'motor' => 'MotorcycleIcon',
            'ojek' => 'MotorcycleIcon',
            'taxi' => 'TaxiIcon',
            'taksi' => 'TaxiIcon',
            'gojek' => 'TaxiIcon',
            'grab' => 'TaxiIcon',
            'uber' => 'TaxiIcon',
            'bus' => 'BusIcon',
            'kereta' => 'TrainIcon',
            'train' => 'TrainIcon',
            'pesawat' => 'PlaneIcon',
            'plane' => 'PlaneIcon',
            'travel' => 'GlobeIcon',
            
            // Utilities
            'utilitas' => 'BoltIcon',
            'utility' => 'BoltIcon',
            'listrik' => 'BoltIcon',
            'electricity' => 'BoltIcon',
            'pln' => 'BoltIcon',
            'air' => 'DropletIcon',
            'pdam' => 'DropletIcon',
            'water' => 'DropletIcon',
            'gas' => 'FlameIcon',
            'pgn' => 'FlameIcon',
            'internet' => 'WifiIcon',
            'wifi' => 'WifiIcon',
            'telepon' => 'PhoneIcon',
            'telephone' => 'PhoneIcon',
            'phone' => 'PhoneIcon',
            'pulsa' => 'PhoneIcon',
            'data' => 'PhoneIcon',
            'voucher' => 'PhoneIcon',
            'telekomunikasi' => 'PhoneIcon',
            
            // Housing
            'perumahan' => 'HomeIcon',
            'housing' => 'HomeIcon',
            'rumah' => 'HomeIcon',
            'house' => 'HomeIcon',
            'apartemen' => 'BuildingIcon',
            'apartment' => 'BuildingIcon',
            'sewa' => 'KeyIcon',
            'rent' => 'KeyIcon',
            'kost' => 'BedIcon',
            'kontrakan' => 'HomeIcon',
            
            // Shopping
            'belanja' => 'ShoppingBagIcon',
            'shopping' => 'ShoppingBagIcon',
            'baju' => 'ShirtIcon',
            'clothes' => 'ShirtIcon',
            'pakaian' => 'ShirtIcon',
            'fashion' => 'ShirtIcon',
            'aksesoris' => 'GemIcon',
            'accessories' => 'GemIcon',
            'elektronik' => 'SmartphoneIcon',
            'electronics' => 'SmartphoneIcon',
            'gadget' => 'SmartphoneIcon',
            
            // Health
            'kesehatan' => 'HeartIcon',
            'health' => 'HeartIcon',
            'dokter' => 'StethoscopeIcon',
            'doctor' => 'StethoscopeIcon',
            'rumah sakit' => 'HeartPulseIcon',
            'hospital' => 'HeartPulseIcon',
            'klinik' => 'MedicalCrossIcon',
            'clinic' => 'MedicalCrossIcon',
            'obat' => 'PillIcon',
            'medicine' => 'PillIcon',
            'apotek' => 'PillIcon',
            'pharmacy' => 'PillIcon',
            'vitamin' => 'PillIcon',
            
            // Education
            'pendidikan' => 'GraduationCapIcon',
            'education' => 'GraduationCapIcon',
            'sekolah' => 'GraduationCapIcon',
            'school' => 'GraduationCapIcon',
            'kuliah' => 'GraduationCapIcon',
            'college' => 'GraduationCapIcon',
            'universitas' => 'GraduationCapIcon',
            'university' => 'GraduationCapIcon',
            'kursus' => 'GraduationCapIcon',
            'course' => 'GraduationCapIcon',
            'buku' => 'BookIcon',
            'book' => 'BookIcon',
            'alat tulis' => 'PencilIcon',
            'stationery' => 'PencilIcon',
            
            // Office supplies
            'kantor' => 'BriefcaseIcon',
            'office' => 'BriefcaseIcon',
            'perlengkapan kantor' => 'BriefcaseIcon',
            'office supplies' => 'BriefcaseIcon',
            'atk' => 'BriefcaseIcon',
            'printer' => 'PrinterIcon',
            'kertas' => 'FileIcon',
            'paper' => 'FileIcon',
            
            // Groceries
            'sembako' => 'ShoppingCartIcon',
            'groceries' => 'ShoppingCartIcon',
            'supermarket' => 'ShoppingCartIcon',
            'minimarket' => 'ShoppingCartIcon',
            'pasar' => 'ShoppingCartIcon',
            'market' => 'ShoppingCartIcon',
            
            // Investment and savings
            'investasi' => 'TrendingUpIcon',
            'investment' => 'TrendingUpIcon',
            'saham' => 'LineChartIcon',
            'stocks' => 'LineChartIcon',
            'reksadana' => 'PieChartIcon',
            'mutual fund' => 'PieChartIcon',
            'emas' => 'CoinsIcon',
            'gold' => 'CoinsIcon',
            'properti' => 'BuildingIcon',
            'property' => 'BuildingIcon',
            'deposito' => 'BankIcon',
            'deposit' => 'BankIcon',
            'tabungan' => 'PiggyBankIcon',
            'savings' => 'PiggyBankIcon',
            'menabung' => 'PiggyBankIcon',
            
            // Insurance
            'asuransi' => 'ShieldIcon',
            'insurance' => 'ShieldIcon',
            'bpjs' => 'ShieldCheckIcon',
            'jiwa' => 'ShieldIcon',
            'life' => 'ShieldIcon',
            'kesehatan' => 'ShieldCheckIcon',
            'health' => 'ShieldCheckIcon',
            'kendaraan' => 'ShieldIcon',
            'vehicle' => 'ShieldIcon',
            
            // Charity and donations
            'amal' => 'HeartHandshakeIcon',
            'charity' => 'HeartHandshakeIcon',
            'donasi' => 'HeartHandshakeIcon',
            'donation' => 'HeartHandshakeIcon',
            'sedekah' => 'HeartHandshakeIcon',
            'sodaqoh' => 'HeartHandshakeIcon',
            'zakat' => 'HeartHandshakeIcon',
            
            // Personal care
            'perawatan' => 'SparklesIcon',
            'care' => 'SparklesIcon',
            'pribadi' => 'UserIcon',
            'personal' => 'UserIcon',
            'salon' => 'ScissorsIcon',
            'potong rambut' => 'ScissorsIcon',
            'haircut' => 'ScissorsIcon',
            'spa' => 'SparklesIcon',
            'massage' => 'SparklesIcon',
            'pijat' => 'SparklesIcon',
            
            // Entertainment
            'hiburan' => 'GamepadIcon',
            'entertainment' => 'GamepadIcon',
            'film' => 'ClapperboardIcon',
            'movie' => 'ClapperboardIcon',
            'bioskop' => 'ClapperboardIcon',
            'cinema' => 'ClapperboardIcon',
            'konser' => 'MusicIcon',
            'concert' => 'MusicIcon',
            'musik' => 'MusicIcon',
            'music' => 'MusicIcon',
            'game' => 'GamepadIcon',
            'games' => 'GamepadIcon',
            'streaming' => 'PlayIcon',
            'netflix' => 'PlayIcon',
            'spotify' => 'MusicIcon',
            'youtube' => 'PlayIcon',
            
            // Smoking
            'rokok' => 'CircleIcon',
            'cigarette' => 'CircleIcon',
            'smoking' => 'CircleIcon',
            'tobacco' => 'CircleIcon',
            
            // Installments and payments
            'cicilan' => 'CreditCardIcon',
            'installment' => 'CreditCardIcon',
            'angsuran' => 'CreditCardIcon',
            'kartu kredit' => 'CreditCardIcon',
            'credit card' => 'CreditCardIcon',
            'pinjaman' => 'CircleDollarSignIcon',
            'loan' => 'CircleDollarSignIcon',
            'kpr' => 'HomeIcon',
            'mortgage' => 'HomeIcon',
            
            // Income and earnings
            'pendapatan' => 'WalletIcon',
            'income' => 'WalletIcon',
            'gaji' => 'WalletIcon',
            'salary' => 'WalletIcon',
            'upah' => 'WalletIcon',
            'wage' => 'WalletIcon',
            'bonus' => 'WalletIcon',
            'komisi' => 'WalletIcon',
            'commission' => 'WalletIcon',
            'honor' => 'WalletIcon',
            'fee' => 'WalletIcon',
            
            // Expense and debt
            'pengeluaran' => 'WalletMinusIcon',
            'expense' => 'WalletMinusIcon',
            'hutang' => 'WalletMinusIcon',
            'utang' => 'WalletMinusIcon',
            'debt' => 'WalletMinusIcon',
            'pinjam' => 'WalletMinusIcon',
            'kredit' => 'WalletMinusIcon',
            
            // Miscellaneous
            'lain-lain' => 'CircleDollarSignIcon',
            'miscellaneous' => 'CircleDollarSignIcon',
            'other' => 'CircleDollarSignIcon',
        ];
        
        // Standard category name mappings
        $standardCategories = [
            'makanan' => 'UtensilsIcon',
            'transportasi' => 'CarIcon',
            'hiburan' => 'GamepadIcon',
            'utilitas' => 'BoltIcon',
            'perumahan' => 'HomeIcon',
            'belanja' => 'ShoppingBagIcon',
            'kesehatan' => 'HeartIcon',
            'pendidikan' => 'GraduationCapIcon',
            'perlengkapan kantor' => 'BriefcaseIcon',
            'sembako' => 'PackageIcon',
            'investasi' => 'TrendingUpIcon',
            'asuransi' => 'ShieldIcon',
            'amal' => 'GiftIcon',
            'perawatan pribadi' => 'SparklesIcon',
            'lain-lain' => 'CircleDollarSignIcon',
            'jajan' => 'CandyIcon',
            'rokok' => 'CircleIcon',
            'telekomunikasi' => 'PhoneIcon',
            'cicilan' => 'CreditCardIcon',
            'tabungan' => 'PiggyBankIcon',
            'pendapatan' => 'WalletIcon',
            'pengeluaran' => 'WalletMinusIcon',
            'hutang' => 'WalletMinusIcon',
            'zakat' => 'HeartHandshakeIcon',
            'sodaqoh' => 'HeartHandshakeIcon',
        ];
        
        // Check if the category name is in the standard categories
        if (isset($standardCategories[$categoryName])) {
            return $standardCategories[$categoryName];
        }
        
        // Check if the category name is in the icon mappings
        if (isset($iconMappings[$categoryName])) {
            return $iconMappings[$categoryName];
        }
        
        // If no specific mapping is found, use the default icon
        return 'CircleDollarSignIcon';
    }
    
    /**
     * Check if all models are currently rate limited
     * 
     * @return bool
     */
    private function areAllModelsRateLimited()
    {
        $models = [
            'llama-3.3-70b-versatile',
            'qwen-2.5-32b',
            'llama-3.1-8b-instant',
            'gemma2-9b-it',
            'mixtral-8x7b-32768'
        ];
        
        foreach ($models as $model) {
            if (!$this->isRateLimited($model)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate a random icon for a new category
     * 
     * @return string
     */
    private function generateRandomIcon()
    {
        $icons = [
            'UtensilsIcon',
            'CarIcon',
            'GamepadIcon',
            'BoltIcon',
            'HomeIcon',
            'ShoppingBagIcon',
            'HeartIcon',
            'GraduationCapIcon',
            'BriefcaseIcon',
            'PackageIcon',
            'TrendingUpIcon',
            'ShieldIcon',
            'GiftIcon',
            'SparklesIcon',
            'CircleDollarSignIcon',
        ];
        
        return $icons[array_rand($icons)];
    }
    
    /**
     * Generate a unique cache key based on the transaction description
     */
    private function generateCacheKey(string $description): string
    {
        $key = $this->cachePrefix . md5($description);
        
        // Store the mapping between cache keys and original descriptions
        $this->storeDescriptionMapping($key, $description);
        
        return $key;
    }
    
    /**
     * Store mapping between cache key and original description
     */
    private function storeDescriptionMapping(string $key, string $description): void
    {
        $descriptionsMap = Cache::get($this->cacheDescriptionsKey, []);
        $descriptionsMap[$key] = $description;
        Cache::put($this->cacheDescriptionsKey, $descriptionsMap, $this->cacheTtl * 60);
    }
    
    /**
     * Get original description from cache key
     */
    private function getOriginalDescription(string $key): ?string
    {
        $descriptionsMap = Cache::get($this->cacheDescriptionsKey, []);
        return $descriptionsMap[$key] ?? null;
    }
    
    /**
     * Invalidate cache
     */
    public function invalidateCache(): void
    {
        // Get all tracked cache keys
        $keys = Cache::get($this->cacheKeysKey, []);
        
        // Clear each cached item
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        // Clear the keys tracking
        Cache::forget($this->cacheKeysKey);
        
        // Clear the descriptions mapping
        Cache::forget($this->cacheDescriptionsKey);
        
        Log::info('AI categorization cache invalidated', [
            'keys_cleared' => count($keys)
        ]);
    }
    
    /**
     * Normalize a transaction description for better matching
     * 
     * @param string $description
     * @return string
     */
    private function normalizeDescription(string $description): string
    {
        // Convert to lowercase
        $normalized = strtolower($description);
        
        // Remove common prefixes that don't affect categorization
        $prefixesToRemove = [
            'pembayaran ', 'payment ', 'transfer ', 'trx ', 'transaksi ', 
            'pembelian ', 'purchase ', 'bayar ', 'beli '
        ];
        
        foreach ($prefixesToRemove as $prefix) {
            if (strpos($normalized, $prefix) === 0) {
                $normalized = substr($normalized, strlen($prefix));
            }
        }
        
        // Remove special characters and extra spaces
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        return trim($normalized);
    }
    
    /**
     * Find a similar cached category based on description similarity
     *
     * @param string $description
     * @param int|null $userId
     * @return Category|null
     */
    private function findSimilarCachedCategory(string $description, $userId = null): ?Category
    {
        // Get all cached keys
        $cacheKeys = Cache::get($this->cacheKeysKey, []);
        $descriptionsMap = Cache::get($this->cacheDescriptionsKey, []);
        
        foreach ($cacheKeys as $key) {
            // Get the original description for this cache key
            $cachedDescription = $descriptionsMap[$key] ?? '';
            
            if (empty($cachedDescription)) {
                continue;
            }
            
            // Calculate similarity
            $similarity = $this->calculateSimilarity($description, $cachedDescription);
            
            if ($similarity >= $this->similarityThreshold) {
                $categoryName = Cache::get($key);
                if (!empty($categoryName)) {
                    Log::debug('Found similar cached category', [
                        'original' => $description,
                        'similar_to' => $cachedDescription,
                        'similarity' => $similarity,
                        'category' => $categoryName
                    ]);
                    
                    // Cache this description with the same category
                    $newCacheKey = $this->generateCacheKey($description);
                    Cache::put($newCacheKey, $categoryName, $this->cacheTtl * 60);
                    
                    // Track this new cache key
                    $cacheKeys = Cache::get($this->cacheKeysKey, []);
                    if (!in_array($newCacheKey, $cacheKeys)) {
                        $cacheKeys[] = $newCacheKey;
                        Cache::put($this->cacheKeysKey, $cacheKeys, $this->cacheTtl * 60);
                    }
                    
                    return $this->findOrCreateCategory($categoryName, $userId);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Calculate similarity between two strings
     * 
     * @param string $str1
     * @param string $str2
     * @return float
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Simple implementation using levenshtein distance
        $levenshtein = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));
        
        if ($maxLength === 0) {
            return 1.0;
        }
        
        return 1.0 - ($levenshtein / $maxLength);
    }
    
    /**
     * Batch categorize multiple transactions
     * 
     * @param array $transactions Array of Transaction objects or description strings
     * @return array Associative array mapping transaction descriptions to Category objects
     */
    public function batchCategorizeTransactions(array $transactions): array
    {
        $results = [];
        $uncategorized = [];
        
        // First pass: check cache for all transactions
        foreach ($transactions as $transaction) {
            $description = is_string($transaction) ? $transaction : $transaction->description;
            $userId = is_string($transaction) ? null : $transaction->user_id;
            $normalizedDescription = $this->normalizeDescription($description);

            // Check direct cache hit
            $cacheKey = $this->generateCacheKey($normalizedDescription);
            if (Cache::has($cacheKey)) {
                $categoryName = Cache::get($cacheKey);
                if (!empty($categoryName)) {
                    $results[$description] = $this->findOrCreateCategory($categoryName, $userId);
                    continue;
                }
            }
            
            // Check for similar descriptions
            $similarCategory = $this->findSimilarCachedCategory($normalizedDescription, $userId);
            if ($similarCategory instanceof Category) {
                $results[$description] = $similarCategory;
                continue;
            }
            
            // If not found in cache, add to uncategorized list
            $uncategorized[] = $description;
        }
        
        // Second pass: process uncategorized transactions in batches
        $batches = array_chunk($uncategorized, $this->batchSize);
        
        foreach ($batches as $batch) {
            // Process this batch
            $batchResults = $this->processBatch($batch);
            
            // Merge results
            foreach ($batchResults as $desc => $category) {
                $results[$desc] = $category;
            }
        }
        
        return $results;
    }
    
    /**
     * Process a batch of transaction descriptions with rate limit handling
     * 
     * @param array $descriptions
     * @return array
     */
    private function processBatch(array $descriptions): array
    {
        $results = [];
        $retryQueue = [];
        $retryCount = 0;
        
        // First attempt
        foreach ($descriptions as $description) {
            // Try to categorize each transaction
            $category = $this->categorizeTransaction($description);
            
            if ($category instanceof Category) {
                $results[$description] = $category;
            } else {
                // Add to retry queue if categorization failed
                $retryQueue[] = $description;
            }
        }
        
        // Retry logic with exponential backoff
        while (!empty($retryQueue) && $retryCount < $this->maxRetries) {
            $retryCount++;
            $waitTime = $this->retryDelay * (2 ** ($retryCount - 1)); // Exponential backoff
            
            Log::info("Waiting {$waitTime} seconds before retry attempt {$retryCount}", [
                'pending_items' => count($retryQueue)
            ]);
            
            // Wait before retrying
            sleep($waitTime);
            
            $currentRetryQueue = $retryQueue;
            $retryQueue = [];
            
            foreach ($currentRetryQueue as $description) {
                // Try again
                $category = $this->categorizeTransaction($description);
                
                if ($category instanceof Category) {
                    $results[$description] = $category;
                } else {
                    // Still failed, add to next retry queue
                    $retryQueue[] = $description;
                }
            }
        }
        
        // Log any items that couldn't be categorized after all retries
        if (!empty($retryQueue)) {
            Log::warning("Failed to categorize some transactions after {$this->maxRetries} retries", [
                'failed_count' => count($retryQueue),
                'failed_descriptions' => $retryQueue
            ]);
        }
        
        return $results;
    }
    
    /**
     * Safely convert a Category object to a string representation for array keys
     * 
     * @param Category $category
     * @return string
     */
    private function categoryToString(Category $category): string
    {
        return 'category_' . $category->id . '_' . $category->name;
    }
    
    /**
     * Preload common transaction descriptions and their categories
     * This can be called during application setup or via a scheduled command
     */
    public function preloadCommonCategories(): void
    {
        $commonTransactions = [
            // Food and Dining
            'Makan siang' => 'Makanan',
            'Makan malam' => 'Makanan',
            'Sarapan' => 'Makanan',
            'Kopi' => 'Makanan',
            'Restoran' => 'Makanan',
            'Warung makan' => 'Makanan',
            'Cafe' => 'Makanan',
            'Jajan' => 'Makanan',
            'Makanan online' => 'Makanan',
            'GoFood' => 'Makanan',
            'GrabFood' => 'Makanan',
            'ShopeeFood' => 'Makanan',
            
            // Transportation
            'Bensin' => 'Transportasi',
            'Parkir' => 'Transportasi',
            'Ojek online' => 'Transportasi',
            'Gojek' => 'Transportasi',
            'Grab' => 'Transportasi',
            'Taksi' => 'Transportasi',
            'Angkot' => 'Transportasi',
            'Bus' => 'Transportasi',
            'Kereta' => 'Transportasi',
            'Pesawat' => 'Transportasi',
            'Tiket transportasi' => 'Transportasi',
            
            // Utilities
            'Listrik' => 'Utilitas',
            'Air' => 'Utilitas',
            'Internet' => 'Utilitas',
            'Telepon' => 'Utilitas',
            'Gas' => 'Utilitas',
            'PLN' => 'Utilitas',
            'PDAM' => 'Utilitas',
            'Indihome' => 'Utilitas',
            'Wifi' => 'Utilitas',
            
            // Shopping
            'Belanja bulanan' => 'Belanja',
            'Supermarket' => 'Belanja',
            'Minimarket' => 'Belanja',
            'Indomaret' => 'Belanja',
            'Alfamart' => 'Belanja',
            'Pakaian' => 'Belanja',
            'Sepatu' => 'Belanja',
            'Aksesoris' => 'Belanja',
            'Elektronik' => 'Belanja',
            
            // Entertainment
            'Bioskop' => 'Hiburan',
            'Konser' => 'Hiburan',
            'Netflix' => 'Hiburan',
            'Spotify' => 'Hiburan',
            'Disney+' => 'Hiburan',
            'Langganan streaming' => 'Hiburan',
            'Game' => 'Hiburan',
            'Buku' => 'Hiburan',
            
            // Health
            'Dokter' => 'Kesehatan',
            'Rumah sakit' => 'Kesehatan',
            'Apotek' => 'Kesehatan',
            'Obat' => 'Kesehatan',
            'Vitamin' => 'Kesehatan',
            'Asuransi kesehatan' => 'Kesehatan',
            'BPJS' => 'Kesehatan',
            
            // Education
            'Sekolah' => 'Pendidikan',
            'Kuliah' => 'Pendidikan',
            'Kursus' => 'Pendidikan',
            'Buku pelajaran' => 'Pendidikan',
            'SPP' => 'Pendidikan',
            'Uang sekolah' => 'Pendidikan',
            
            // Housing
            'Sewa rumah' => 'Perumahan',
            'Sewa kost' => 'Perumahan',
            'Cicilan rumah' => 'Perumahan',
            'KPR' => 'Perumahan',
            'Perabotan' => 'Perumahan',
            'Perbaikan rumah' => 'Perumahan',
            
            // Telecommunications
            'Pulsa' => 'Telekomunikasi',
            'Paket data' => 'Telekomunikasi',
            'Telkomsel' => 'Telekomunikasi',
            'XL' => 'Telekomunikasi',
            'Indosat' => 'Telekomunikasi',
            'Smartfren' => 'Telekomunikasi',
            
            // Income
            'Gaji' => 'Pendapatan',
            'Bonus' => 'Pendapatan',
            'Komisi' => 'Pendapatan',
            'Freelance' => 'Pendapatan',
            'Penjualan' => 'Pendapatan',
            'Dividen' => 'Pendapatan',
            'Bunga' => 'Pendapatan',
            
            // Investments
            'Saham' => 'Investasi',
            'Reksa dana' => 'Investasi',
            'Emas' => 'Investasi',
            'Deposito' => 'Investasi',
            'Obligasi' => 'Investasi',
            'Cryptocurrency' => 'Investasi',
            'P2P Lending' => 'Investasi',
            
            // Charity - Updated to use Zakat or Sodaqoh
            'Donasi' => 'Amal',
            'Zakat' => 'Zakat',
            'Zakat penghasilan' => 'Zakat',
            'Sedekah' => 'Sodaqoh',
            'Sumbangan' => 'Amal',
            
            // Installments
            'Cicilan motor' => 'Cicilan',
            'Cicilan mobil' => 'Cicilan',
            'Cicilan gadget' => 'Cicilan',
            'Cicilan kartu kredit' => 'Cicilan',
            'Pinjaman' => 'Cicilan',
            
            // Savings
            'Tabungan' => 'Tabungan',
            'Transfer ke tabungan' => 'Tabungan',
            'Dana darurat' => 'Tabungan',
        ];
        
        $count = 0;
        
        foreach ($commonTransactions as $description => $category) {
            $normalizedDescription = $this->normalizeDescription($description);
            $cacheKey = $this->generateCacheKey($normalizedDescription);
            
            // Only add if not already in cache
            if (!Cache::has($cacheKey)) {
                Cache::put($cacheKey, $category, $this->cacheTtl * 60);
                
                // Track this cache key
                $cacheKeys = Cache::get($this->cacheKeysKey, []);
                if (!in_array($cacheKey, $cacheKeys)) {
                    $cacheKeys[] = $cacheKey;
                    Cache::put($this->cacheKeysKey, $cacheKeys, $this->cacheTtl * 60);
                }
                
                $count++;
            }
        }
        
        Log::info('Preloaded common categories', [
            'categories_added' => $count,
            'total_categories' => count($commonTransactions)
        ]);
    }
    
    /**
     * Check if a model is currently rate limited
     * 
     * @param string $model
     * @return bool
     */
    private function isRateLimited(string $model): bool
    {
        $cacheKey = $this->rateLimitCacheKey . $model;
        return Cache::has($cacheKey);
    }
    
    /**
     * Mark a model as rate limited based on the exception
     * 
     * @param string $model
     * @param \Exception $exception
     * @return void
     */
    private function markRateLimited(string $model, \Exception $exception): void
    {
        $cacheKey = $this->rateLimitCacheKey . $model;
        
        // Try to extract retry-after from the exception
        $retryAfter = $this->extractRetryAfter($exception);
        
        // If we couldn't extract retry-after, use default value
        if ($retryAfter === null) {
            $retryAfter = $this->retryDelay;
        }
        
        // Store rate limit info in cache
        Cache::put($cacheKey, true, $retryAfter);
        
        Log::info("Model $model rate limited for $retryAfter seconds", [
            'model' => $model,
            'retry_after' => $retryAfter,
            'exception' => $exception->getMessage()
        ]);
    }
    
    /**
     * Extract retry-after value from exception
     * 
     * @param \Exception $exception
     * @return int|null
     */
    private function extractRetryAfter(\Exception $exception): ?int
    {
        // Try to extract retry-after from exception message or data
        $message = $exception->getMessage();
        
        // Try to parse retry-after from the message
        if (preg_match('/retry[- ]after:?\s*(\d+)/i', $message, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
    
    /**
     * Check if an exception is related to rate limiting
     *
     * @param \Exception $exception
     * @return bool
     */
    private function isRateLimitException(\Exception $exception): bool
    {
        // Check if the exception is related to rate limiting (HTTP 429)
        if (method_exists($exception, 'getCode') && $exception->getCode() == 429) {
            return true;
        }
        
        // Check message for rate limit keywords
        $message = $exception->getMessage();
        return (
            stripos($message, 'rate limit') !== false ||
            stripos($message, 'too many requests') !== false ||
            stripos($message, '429') !== false
        );
    }
    
    /**
     * Update rate limit information from response headers
     * 
     * @param string $model
     * @param mixed $response
     * @return void
     */
    private function updateRateLimitInfo(string $model, $response): void
    {
        // Extract rate limit headers from response if available
        $headers = $this->extractResponseHeaders($response);
        
        if (empty($headers)) {
            return;
        }
        
        // Store rate limit information
        $rateLimitInfo = [
            'model' => $model,
            'timestamp' => time(),
        ];
        
        // Extract relevant headers
        $headerMapping = [
            'x-ratelimit-limit-requests' => 'limit_requests',
            'x-ratelimit-limit-tokens' => 'limit_tokens',
            'x-ratelimit-remaining-requests' => 'remaining_requests',
            'x-ratelimit-remaining-tokens' => 'remaining_tokens',
            'x-ratelimit-reset-requests' => 'reset_requests',
            'x-ratelimit-reset-tokens' => 'reset_tokens',
        ];
        
        foreach ($headerMapping as $header => $key) {
            if (isset($headers[$header])) {
                $rateLimitInfo[$key] = $headers[$header];
            }
        }
        
        // Store the rate limit info in cache
        Cache::put("groq_rate_limit_info_{$model}", $rateLimitInfo, 60); // Store for 1 hour
        
        // If we're close to the rate limit, mark the model as rate limited proactively
        $this->checkProactiveRateLimiting($model, $rateLimitInfo);
    }
    
    /**
     * Extract headers from response object
     * 
     * @param mixed $response
     * @return array
     */
    private function extractResponseHeaders($response): array
    {
        $headers = [];
        
        // Handle different response types
        if (is_object($response)) {
            // If response has headers property or method
            if (property_exists($response, 'headers')) {
                $headers = (array) $response->headers;
            } elseif (method_exists($response, 'getHeaders')) {
                $headers = $response->getHeaders();
            } elseif (method_exists($response, 'headers')) {
                $headers = $response->headers();
            }
        } elseif (is_array($response) && isset($response['headers'])) {
            $headers = $response['headers'];
        }
        
        // Normalize header keys to lowercase
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }
        
        return $normalizedHeaders;
    }
    
    /**
     * Check if we should proactively rate limit based on remaining quota
     * 
     * @param string $model
     * @param array $rateLimitInfo
     * @return void
     */
    private function checkProactiveRateLimiting(string $model, array $rateLimitInfo): void
    {
        // Thresholds for proactive rate limiting (percentage of remaining quota)
        $requestThreshold = 0.05; // 5% of requests remaining
        $tokenThreshold = 0.05; // 5% of tokens remaining
        
        // Check if we're close to request limit
        if (isset($rateLimitInfo['remaining_requests'], $rateLimitInfo['limit_requests'])) {
            $requestRatio = $rateLimitInfo['remaining_requests'] / $rateLimitInfo['limit_requests'];
            if ($requestRatio <= $requestThreshold) {
                // Calculate how long to wait based on reset time
                $waitTime = $this->parseResetTime($rateLimitInfo['reset_requests'] ?? '60s');
                $this->markRateLimitedForDuration($model, $waitTime);
                
                Log::warning("Proactively rate limiting $model due to low request quota", [
                    'remaining_requests' => $rateLimitInfo['remaining_requests'],
                    'limit_requests' => $rateLimitInfo['limit_requests'],
                    'ratio' => $requestRatio,
                    'wait_time' => $waitTime
                ]);
                
                return;
            }
        }
        
        // Check if we're close to token limit
        if (isset($rateLimitInfo['remaining_tokens'], $rateLimitInfo['limit_tokens'])) {
            $tokenRatio = $rateLimitInfo['remaining_tokens'] / $rateLimitInfo['limit_tokens'];
            if ($tokenRatio <= $tokenThreshold) {
                // Calculate how long to wait based on reset time
                $waitTime = $this->parseResetTime($rateLimitInfo['reset_tokens'] ?? '60s');
                $this->markRateLimitedForDuration($model, $waitTime);
                
                Log::warning("Proactively rate limiting $model due to low token quota", [
                    'remaining_tokens' => $rateLimitInfo['remaining_tokens'],
                    'limit_tokens' => $rateLimitInfo['limit_tokens'],
                    'ratio' => $tokenRatio,
                    'wait_time' => $waitTime
                ]);
            }
        }
    }
    
    /**
     * Mark a model as rate limited for a specific duration
     * 
     * @param string $model
     * @param int $seconds
     * @return void
     */
    private function markRateLimitedForDuration(string $model, int $seconds): void
    {
        $cacheKey = $this->rateLimitCacheKey . $model;
        Cache::put($cacheKey, true, $seconds);
    }
    
    /**
     * Parse reset time string to seconds
     * 
     * @param string $resetTime
     * @return int
     */
    private function parseResetTime(string $resetTime): int
    {
        // Default to 60 seconds if we can't parse
        $seconds = 60;
        
        // Parse time formats like "2m59.56s" or "7.66s"
        if (preg_match('/(?:(\d+)m)?(?:([\d.]+)s)?/', $resetTime, $matches)) {
            $minutes = isset($matches[1]) ? (int) $matches[1] : 0;
            $secs = isset($matches[2]) ? (float) $matches[2] : 0;
            $seconds = ($minutes * 60) + ceil($secs);
        }
        
        // Ensure we have at least a minimum wait time
        return max($seconds, 5);
    }
    
    /**
     * Check if a transaction is zakat-related
     * 
     * @param string $description
     * @return bool
     */
    private function isZakatTransaction(string $description): bool
    {
        $normalized = strtolower($description);
        return (strpos($normalized, 'zakat') !== false);
    }
    
    /**
     * Check if a transaction is THR-related (holiday allowance)
     * 
     * @param string $description
     * @return bool
     */
    private function isThrTransaction(string $description): bool
    {
        $normalized = strtolower($description);
        return (strpos($normalized, 'thr') !== false);
    }
    
    /**
     * Invalidate cache for specific category types
     * 
     * @param string $categoryType Type of category to invalidate (e.g., 'zakat', 'amal')
     * @return int Number of cache entries invalidated
     */
    public function invalidateCategoryTypeCache(string $categoryType): int
    {
        $categoryType = strtolower($categoryType);
        $count = 0;
        
        // Get all tracked cache keys
        $keys = Cache::get($this->cacheKeysKey, []);
        $descriptionsMap = Cache::get($this->cacheDescriptionsKey, []);
        
        foreach ($keys as $key) {
            // Get the original description for this cache key
            $description = $descriptionsMap[$key] ?? '';
            
            if (empty($description)) {
                continue;
            }
            
            // Check if this description contains the category type
            if (strpos(strtolower($description), $categoryType) !== false) {
                // Get the current category
                $currentCategory = Cache::get($key);
                
                // If it's categorized as something we want to change
                if ($categoryType === 'zakat' && $currentCategory !== 'Zakat') {
                    // Update the cache with the correct category
                    Cache::put($key, 'Zakat', $this->cacheTtl * 60);
                    $count++;
                }
                
                // You can add more category type corrections here
            }
        }
        
        Log::info("Invalidated $count cache entries for category type: $categoryType");
        return $count;
    }
    
    /**
     * Fix zakat categorizations in cache
     * 
     * @return int Number of entries fixed
     */
    public function fixZakatCategorizations(): int
    {
        return $this->invalidateCategoryTypeCache('zakat');
    }
    
    /**
     * Map single-word categories to standard categories
     * 
     * @param string $categoryName
     * @return string Mapped category name
     */
    private function mapSingleWordCategory(string $categoryName): string
    {
        // Special case for Indonesian foods - always categorize as Makanan
        if ($this->isIndonesianFood($categoryName)) {
            return 'Makanan';
        }
        
        // Special case for jajan-related terms - always categorize as Jajan
        if ($this->isJajanRelated($categoryName)) {
            return 'Jajan';
        }
        
        // Special case for Hutang - categorize as Hutang (not Pengeluaran)
        if ($this->isHutangRelated($categoryName) && !$this->isBayarHutangRelated($categoryName)) {
            return 'Hutang';
        }
        
        // Special case for utilities - categorize as Utilitas
        // But ensure 'Uti' (Javanese for mother) is not incorrectly categorized
        if ($this->isUtilitasRelated($categoryName)) {
            return 'Utilitas';
        }
        
        // Special cases for income-related terms that should always map to Pendapatan
        $incomeTerms = [
            'Honor', 'Dana Masuk', 'Dana', 'Uang', 'Pemasukan', 'Penerimaan', 
            'Pendapatan', 'Gaji', 'Bonus', 'Upah', 'Salary', 'Income', 'Fee', 
            'Komisi', 'Royalti', 'Dividen', 'THR', 'Cashback', 'Refund', 
            'Reward', 'Profit', 'Laba', 'Hadiah', 'Transfer Masuk', 'Bayar Hutang',
            'Bayar Utang', 'Jual', 'Penjualan', 'Penyewaan', 'Sewa', 'Pembayaran', 'Honorarium'
        ];
        
        // Check for income terms but make sure it's not jajan-related
        if ((in_array($categoryName, $incomeTerms) || 
            strpos(strtolower($categoryName), 'dana masuk') !== false ||
            strpos(strtolower($categoryName), 'pemasukan') !== false ||
            strpos(strtolower($categoryName), 'gaji') !== false ||
            strpos(strtolower($categoryName), 'honor') !== false ||
            strpos(strtolower($categoryName), 'jual') !== false ||
            strpos(strtolower($categoryName), 'bayar hutang') !== false ||
            strpos(strtolower($categoryName), 'bayar utang') !== false) && 
            !$this->isJajanRelated($categoryName)) {
            return 'Pendapatan';
        }
        
        // Special case: if the category is 'Tempe', ensure it goes to 'Makanan' category
        if ($categoryName === 'Tempe') {
            return 'Makanan';
        }
        
        // Special case: if the category is 'Uti', ensure it doesn't go to 'Utilitas' category
        if (strtolower($categoryName) === 'uti') {
            return 'Lain-lain';  // Or another appropriate category
        }

        // If already a standard category, return as is
        $standardCategories = [
            'Makanan', 'Transportasi', 'Hiburan', 'Utilitas', 'Perumahan', 
            'Belanja', 'Kesehatan', 'Pendidikan', 'Perlengkapan Kantor', 
            'Sembako', 'Investasi', 'Asuransi', 'Amal', 'Perawatan Pribadi', 
            'Lain-lain', 'Jajan', 'Rokok', 'Telekomunikasi', 'Cicilan',
            'Tabungan', 'Pendapatan', 'Zakat', 'Sodaqoh', 'Pengeluaran', 'Hutang'
        ];
        
        if (in_array($categoryName, $standardCategories)) {
            return $categoryName;
        }
        
        // Check for common mistranslations
        $mistranslationMap = [
            'Temple' => 'Hiburan',
            'Bensin' => 'Transportasi', 
            'Servis' => 'Transportasi',
            'SPBU' => 'Transportasi'
        ];
        
        if (isset($mistranslationMap[$categoryName])) {
            Log::debug('Correcting mistranslated category', [
                'original' => $categoryName,
                'corrected_to' => $mistranslationMap[$categoryName]
            ]);
            return $mistranslationMap[$categoryName];
        }

        // Define mapping of single words to standard categories
        $categoryMapping = [
            // Income related (expanded)
            'Gaji' => 'Pendapatan',
            'Bonus' => 'Pendapatan',
            'Upah' => 'Pendapatan',
            'Honor' => 'Pendapatan',
            'Dana' => 'Pendapatan',
            'Uang' => 'Pendapatan', 
            'Pemasukan' => 'Pendapatan',
            'Penerimaan' => 'Pendapatan',
            'Salary' => 'Pendapatan',
            'Income' => 'Pendapatan',
            'Fee' => 'Pendapatan',
            'Komisi' => 'Pendapatan',
            'Royalti' => 'Pendapatan',
            'Dividen' => 'Pendapatan',
            'THR' => 'Pendapatan',
            'Cashback' => 'Pendapatan',
            'Refund' => 'Pendapatan',
            'Reward' => 'Pendapatan',
            'Profit' => 'Pendapatan',
            'Laba' => 'Pendapatan',
            'Hadiah' => 'Pendapatan',
            
            // Other categories continued as before...
            'Tahu' => 'Makanan',
            'Tempe' => 'Makanan',
            'Bakso' => 'Makanan',
            'Mie' => 'Makanan',
            'Nasi' => 'Makanan',
            'Ayam' => 'Makanan',
            'Ikan' => 'Makanan',
            'Roti' => 'Makanan',
            'Kopi' => 'Makanan',
            'Soto' => 'Makanan',
            'Martabak' => 'Makanan',
            'Seafood' => 'Makanan',
            'Bubur' => 'Makanan',
            'Sate' => 'Makanan',
            'Burger' => 'Makanan',
            'Pizza' => 'Makanan',
            'Catering' => 'Makanan',
            'Gorengan' => 'Jajan',
            'Camilan' => 'Jajan',
            'Snack' => 'Jajan',
            'Keripik' => 'Jajan',
            'Kerupuk' => 'Jajan',
            'Cemilan' => 'Jajan',
            'Jajanan' => 'Jajan',
            'Coklat' => 'Jajan',
            'Permen' => 'Jajan',
            'Donat' => 'Jajan',
            'Baju' => 'Belanja',
            'Celana' => 'Belanja',
            'Sepatu' => 'Belanja',
            'Elektronik' => 'Belanja',
            'Tas' => 'Belanja',
            'Topi' => 'Belanja',
            'Kacamata' => 'Belanja',
            'Jaket' => 'Belanja',
            'Hoodie' => 'Belanja',
            'Kemeja' => 'Belanja',
            'Furnitur' => 'Belanja',
            'Kosmetik' => 'Belanja',
            'Parfum' => 'Belanja',
            'Souvenir' => 'Belanja',
            'Merchandise' => 'Belanja',
            'Perhiasan' => 'Belanja',
            'Sabun' => 'Sembako',
            'Deterjen' => 'Sembako',
            'Sampo' => 'Sembako',
            'Pasta' => 'Sembako',
            'Sikat' => 'Sembako',
            'Tissue' => 'Sembako',
            'Pewangi' => 'Sembako',
            'Pembersih' => 'Sembako',
            'Sapu' => 'Sembako',
            'Pel' => 'Sembako',
        ];
        
        // Check if this is a single word category that needs mapping
        if (isset($categoryMapping[$categoryName])) {
            Log::debug('Mapping single-word category', [
                'original' => $categoryName,
                'mapped_to' => $categoryMapping[$categoryName]
            ]);
            return $categoryMapping[$categoryName];
        }
        
        // If not found in the mapping and contains only one word, mark as Lain-lain to be safe
        if (!str_contains($categoryName, ' ') && strlen($categoryName) < 15) {
            // If it's a short single word category we don't recognize, map to Lain-lain
            Log::debug('Unknown single-word category mapped to Lain-lain', [
                'original' => $categoryName
            ]);
            return 'Lain-lain';
        }
        
        return $categoryName;
    }
    
    /**
     * Check if a term is related to Indonesian food
     * 
     * @param string $term
     * @return bool
     */
    private function isIndonesianFood(string $term): bool
    {
        $indonesianFoods = [
            'dawet', 'soto', 'pecel', 'gado-gado', 'gadogado', 'nasi goreng', 'mie ayam', 
            'bakso', 'sate', 'rendang', 'gudeg', 'pempek', 'rujak', 'rawon', 'ketoprak',
            'lontong', 'opor', 'sambal', 'ayam goreng', 'ayam bakar', 'nasi padang',
            'sop', 'gulai', 'empal', 'serundeng', 'semur', 'batagor', 'siomay',
            'martabak', 'terang bulan', 'bubur', 'ketupat', 'bakmi', 'bakwan', 'lumpia',
            'tahu', 'tempe', 'sayur', 'capcay', 'kwetiau', 'bihun', 'lalapan', 'rica',
            'sop buntut', 'soto betawi', 'soto madura', 'soto lamongan', 'kuah',
            'bubur ayam', 'nasi uduk', 'nasi liwet', 'nasi kuning', 'nasi kebuli',
            'nasi pecel', 'nasi campur', 'nasi bungkus', 'lauk', 'sayur', 'kerupuk'
        ];
        
        $term = strtolower(trim($term));
        
        foreach ($indonesianFoods as $food) {
            if (strpos($term, $food) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a term is related to jajan (snacks)
     * 
     * @param string $term
     * @return bool
     */
    private function isJajanRelated(string $term): bool
    {
        $jajanTerms = ['jajan', 'cemilan', 'snack', 'gorengan', 'camilan', 'keripik', 'kerupuk', 'jajanan'];
        
        $term = strtolower(trim($term));
        
        foreach ($jajanTerms as $jajanTerm) {
            if (strpos($term, $jajanTerm) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a term is related to hutang (debt)
     * 
     * @param string $term
     * @return bool
     */
    private function isHutangRelated(string $term): bool
    {
        $hutangTerms = ['hutang', 'utang', 'pinjam', 'pinjaman', 'cicil', 'cicilan', 'kredit', 'credit'];
        
        $term = strtolower(trim($term));
        
        foreach ($hutangTerms as $hutangTerm) {
            if (strpos($term, $hutangTerm) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a term is specifically related to paying off debt (Bayar Hutang)
     * 
     * @param string $term
     * @return bool
     */
    private function isBayarHutangRelated(string $term): bool
    {
        $paymentTerms = [
            'bayar hutang', 'bayar utang', 'membayar hutang', 'membayar utang', 
            'lunasi hutang', 'lunasi utang', 'pelunasan hutang', 'pelunasan utang',
            'lunas hutang', 'lunas utang'
        ];
        
        $term = strtolower(trim($term));
        
        foreach ($paymentTerms as $paymentTerm) {
            if (strpos($term, $paymentTerm) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a term is related to utilities
     * 
     * @param string $term
     * @return bool
     */
    private function isUtilitasRelated(string $term): bool
    {
        $utilitasTerms = [
            'utilitas', 'utility', 'listrik', 'electricity', 'pln', 'air', 'water',
            'pdam', 'gas', 'pgn', 'tagihan', 'bill', 'iuran', 'token', 'pulsa listrik'
        ];
        
        $term = strtolower(trim($term));
        
        // Special case: 'uti' alone (or at word boundaries) should not be considered utilities-related
        // as it means "mother" in Javanese
        if ($term === 'uti' || preg_match('/\buti\b/', $term)) {
            return false;
        }
        
        foreach ($utilitasTerms as $utilitasTerm) {
            if (strpos($term, $utilitasTerm) !== false) {
                return true;
            }
        }
        
        return false;
    }
} 
