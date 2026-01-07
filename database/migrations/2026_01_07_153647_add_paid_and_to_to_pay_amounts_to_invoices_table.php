<?php

use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('remaining_to_pay')->nullable()->after('total_vat_exclusive');
            $table->unsignedBigInteger('total_to_pay')->nullable()->after('total_vat_exclusive');
        });

        Invoice::query()->with(['payments', 'lines'])->eachById(fn (Invoice $invoice) => $invoice->calculateTotals());
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('remaining_to_pay');
            $table->dropColumn('total_to_pay');
        });
    }
};
