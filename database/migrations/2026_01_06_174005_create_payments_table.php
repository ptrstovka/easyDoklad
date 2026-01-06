<?php

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->foreignId('recorded_by_id')->nullable()->constrained('users');
            $table->string('method');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Invoice::query()->eachById(function (Invoice $invoice) {
            if ($invoice->paid && ($amount = $invoice->getAmountToPay())) {
                $invoice->payments()->create([
                    'amount' => $amount,
                    'received_at' => $invoice->updated_at,
                    'method' => PaymentMethod::BankTransfer,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
