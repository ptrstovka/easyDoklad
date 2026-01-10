<?php

use App\Models\BankTransactionAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transaction_accounts', function (Blueprint $table) {
            $table->string('handle')->after('uuid')->nullable();
        });

        BankTransactionAccount::query()
            ->withTrashed()
            ->eachById(function (BankTransactionAccount $account) {
                $account->update(['handle' => BankTransactionAccount::randomHandle()]);
            });

        Schema::table('bank_transaction_accounts', function (Blueprint $table) {
            $table->string('handle')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transaction_accounts', function (Blueprint $table) {
            $table->dropColumn('handle');
        });
    }
};
