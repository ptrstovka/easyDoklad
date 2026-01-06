<?php


namespace App\Http\Controllers\Invoice;


use App\Http\Requests\IssueInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueInvoiceController
{
    public function __invoke(IssueInvoiceRequest $request, Invoice $invoice)
    {
        /** @var Invoice $invoice */
        $invoice = DB::transaction(fn () => $request->updateInvoice($invoice));

        try {
            Cache::lock("IssueInvoice:" . $invoice->account->id, 10)
                ->block(5, fn () => DB::transaction(fn () => $invoice->issue()));
        } catch (LockTimeoutException) {
            return throw ValidationException::withMessages([
                'public_invoice_number' => 'Nepodarilo sa vystaviť faktúru. Skúste to znovu.'
            ]);
        }

        return back();
    }
}
