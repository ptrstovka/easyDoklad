<?php


namespace App\Http\Controllers\Invoice;


use App\Models\Invoice;
use Illuminate\Support\Facades\Gate;

class InvoiceLockController
{
    public function store(Invoice $invoice)
    {
        Gate::authorize('update', $invoice);

        abort_if($invoice->draft, 400, "Invoice draft cannot be locked");
        abort_if($invoice->locked, 400, "Locked invoice cannot be locked");

        $invoice->preventModifications();

        return back();
    }

    public function destroy(Invoice $invoice)
    {
        Gate::authorize('update', $invoice);

        abort_unless($invoice->locked, 400, "Already unlocked invoice cannot be unlocked");

        $invoice->allowModifications();

        return back();
    }
}
