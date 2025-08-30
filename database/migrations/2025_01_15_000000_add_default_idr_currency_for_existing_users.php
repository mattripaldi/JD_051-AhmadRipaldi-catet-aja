<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Currency;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $accounts = $user->accounts;

            if ($accounts->isEmpty()) {
                $account = $user->accounts()->create([
                    'name' => 'Default Account',
                    'description' => 'Default account created automatically',
                ]);
                $accounts = collect([$account]);
            }

            foreach ($accounts as $account) {
                $existingIdr = Currency::where('user_id', $user->id)
                    ->where('account_id', $account->id)
                    ->where('name', 'IDR')
                    ->first();

                if (!$existingIdr) {
                    Currency::create([
                        'user_id' => $user->id,
                        'account_id' => $account->id,
                        'name' => 'IDR',
                        'symbol' => 'Rp',
                        'exchange_rate' => 1.0,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
