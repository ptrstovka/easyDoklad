<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class InvoicePaid implements ShouldDispatchAfterCommit
{
    public function __construct(
        public Invoice $invoice
    ) { }
}
