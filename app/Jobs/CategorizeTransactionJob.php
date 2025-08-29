<?php

namespace App\Jobs;

use App\Models\Income;
use App\Models\Outcome;
use App\Services\AiCategorizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CategorizeTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The transaction instance (Income or Outcome model).
     *
     * @var \App\Models\Income|\App\Models\Outcome
     */
    protected $transaction;

    /**
     * Whether to force recategorization even if the transaction already has a category.
     *
     * @var bool
     */
    protected $forceRecategorize;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Income|\App\Models\Outcome  $transaction
     * @param  bool  $forceRecategorize
     * @return void
     */
    public function __construct($transaction, bool $forceRecategorize = false)
    {
        $this->transaction = $transaction;
        $this->forceRecategorize = $forceRecategorize;
    }

    /**
     * Execute the job.
     *
     * @param  \App\Services\AiCategorizationService  $aiService
     * @return void
     */
    public function handle(AiCategorizationService $aiService): void
    {
        try {
            // Skip if transaction already has a category and we're not forcing recategorization
            if ($this->transaction->category_id && !$this->forceRecategorize) {
                Log::info('Transaction already has a category and recategorization not forced', [
                    'transaction_id' => $this->transaction->id,
                    'category_id' => $this->transaction->category_id
                ]);
                return;
            }

            // Use AI service to categorize the transaction
            $category = $aiService->categorizeTransaction($this->transaction);
            
            if ($category !== null) {
                // Update the transaction with the category and mark as completed
                $this->transaction->category_id = $category->id;
                $this->transaction->categorization_status = 'completed';
                $this->transaction->save();
                
                Log::info('Transaction categorized successfully', [
                    'transaction_id' => $this->transaction->id,
                    'category_id' => $category->id,
                    'category_name' => $category->name
                ]);
            } else {
                // Even if we couldn't categorize, mark as completed to avoid endless polling
                $this->transaction->categorization_status = 'completed';
                $this->transaction->save();
                
                Log::warning('Could not categorize transaction', [
                    'transaction_id' => $this->transaction->id,
                    'description' => $this->transaction->description
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error categorizing transaction', [
                'transaction_id' => $this->transaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
