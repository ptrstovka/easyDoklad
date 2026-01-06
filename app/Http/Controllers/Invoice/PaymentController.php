<?php


namespace App\Http\Controllers\Invoice;


use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Brick\Money\Money;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController
{
    public function store(Request $request, Invoice $invoice)
    {
        Gate::allows('update', $invoice);

        abort_if($invoice->draft, 400, "Draft invoices cannot be modified");

        $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'received_at' => ['required', 'date_format:Y-m-d', Rule::date()->afterOrEqual($invoice->issued_at->startOfDay())],
        ]);

        $amount = Money::ofMinor($request->input('amount'), $invoice->currency);
        $method = $request->enum('method', PaymentMethod::class);
        $receivedAt = $request->date('received_at', 'Y-m-d');

        try {
            $invoice->whileLocked(fn () => DB::transaction(fn () => $invoice->addPayment($amount, $method, $receivedAt, $request->user())));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'amount' => 'Nepodarilo sa pridať platbu k faktúre. Skúste to znovu.',
            ]);
        }

        return back();
    }
}
