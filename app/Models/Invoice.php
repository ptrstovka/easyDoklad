<?php

namespace App\Models;

use App\Casts\AsMoney;
use App\Enums\PaymentMethod;
use App\Events\InvoicePaid;
use App\Mail\SendInvoiceMail;
use App\Models\Concerns\HasUuid;
use App\Support\MoneyUtils;
use App\Support\NumberSequenceFormatter;
use App\Support\VatBreakdownLine;
use App\Templating\InvoiceSerializer;
use App\Templating\SerializerOptions;
use Brick\Math\BigNumber;
use Brick\Money\Currency;
use Brick\Money\Exception\MoneyMismatchException;
use Brick\Money\Money;
use Bysqr\BankAccount;
use Bysqr\Pay;
use Bysqr\Payment as PendingPayment;
use Bysqr\PaymentOption;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @property \App\Models\Upload|null $signature
 * @property \App\Models\Upload|null $logo
 * @property \App\Models\Company $supplier
 * @property \App\Models\Company $customer
 * @property \Carbon\Carbon|null $issued_at
 * @property \Carbon\Carbon|null $supplied_at
 * @property \Carbon\Carbon|null $payment_due_to
 * @property PaymentMethod $payment_method
 * @property string|null $variable_symbol
 * @property string|null $specific_symbol
 * @property string|null $constant_symbol
 * @property boolean $show_pay_by_square
 * @property boolean $vat_reverse_charge
 * @property string|null $public_invoice_number
 * @property string $currency
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceLine> $lines
 * @property \App\Models\Account $account
 * @property boolean $draft
 * @property boolean $sent
 * @property boolean $paid
 * @property boolean $locked
 * @property boolean $vat_enabled
 * @property \App\Models\DocumentTemplate $template
 * @property string|null $footer_note
 * @property string|null $issued_by
 * @property string|null $issued_by_email
 * @property string|null $issued_by_phone_number
 * @property string|null $issued_by_website
 * @property \App\Models\NumberSequence|null $numberSequence
 * @property int $invoice_number
 * @property Money|null $total_vat_inclusive
 * @property Money|null $total_vat_exclusive
 * @property Money|null $total_to_pay
 * @property Money|null $remaining_to_pay
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 */
class Invoice extends Model
{
    use HasUuid;

    protected $guarded = false;

    protected function casts(): array
    {
        return [
            'draft' => 'boolean',
            'sent' => 'boolean',
            'paid' => 'boolean',
            'locked' => 'boolean',
            'issued_at' => 'date',
            'supplied_at' => 'date',
            'payment_due_to' => 'date',
            'payment_method' => PaymentMethod::class,
            'show_pay_by_square' => 'boolean',
            'vat_reverse_charge' => 'boolean',
            'vat_enabled' => 'boolean',
            'total_vat_inclusive' => AsMoney::class,
            'total_vat_exclusive' => AsMoney::class,
            'total_to_pay' => AsMoney::class,
            'remaining_to_pay' => AsMoney::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Invoice $invoice) {
            $invoice->lines->each->delete();
            $invoice->payments->each->delete();
        });

        static::deleted(function (Invoice $invoice) {
            $invoice->signature?->delete();
            $invoice->logo?->delete();
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function numberSequence(): BelongsTo
    {
        return $this->belongsTo(NumberSequence::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Get lines sorted by a position attribute.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceLine>
     */
    public function getSortedLines(): EloquentCollection
    {
        return $this->lines->sortBy('position')->values();
    }

    /**
     * Calculate invoice totals.
     */
    public function calculateTotals(): void
    {
        $sum = fn (Collection $prices) => MoneyUtils::sum($this->currency, ...$prices->filter()->values());

        $this->total_vat_inclusive = $sum($this->lines->map->total_price_vat_inclusive);
        $this->total_vat_exclusive = $sum($this->lines->map->total_price_vat_exclusive);
        $this->total_to_pay = $this->calculateTotalToPay();
        $this->remaining_to_pay = $this->calculateRemainingToPay();

        $recentlyPaid = false;

        if ($this->remaining_to_pay) {
            if ($this->remaining_to_pay->isZero() && !$this->paid) {
                $this->paid = true;
                $recentlyPaid = true;
            } else if (!$this->remaining_to_pay->isZero() && $this->paid) {
                $this->paid = false;
            }
        }

        $this->save();

        if ($recentlyPaid) {
            event(new InvoicePaid($this->withoutRelations()));
        }
    }

    /**
     * Issue an invoice.
     */
    public function issue(): void
    {
        $formatter = new NumberSequenceFormatter(
            format: $this->account->invoice_numbering_format,
            date: $this->supplied_at,
        );

        $sequenceToken = $formatter->formatSequenceToken();

        /** @var \App\Models\NumberSequence $sequence */
        $sequence = $this->account->numberSequences()->firstWhere('sequence_token', $sequenceToken) ?: $this->account->numberSequences()->create([
            'sequence_token' => $sequenceToken,
            'format' => $formatter->getFormat(),
            'next_number' => 1,
        ]);

        // If the invoice number was already set by the user, we won't be touching that number.
        if (! $this->public_invoice_number) {
            $this->public_invoice_number = $formatter->formatNumber($sequence->next_number);
        }

        // If the invoice does not have variable symbol, we generate a new one.
        if (! $this->variable_symbol) {
            $variableSymbolFormatter = new NumberSequenceFormatter(
                format: $this->account->invoice_variable_symbol_format,
                date: $this->supplied_at,
            );

            $this->variable_symbol = $variableSymbolFormatter->formatNumber($sequence->next_number);
        }

        // Mark the invoice as issued.
        $this->draft = false;
        $this->locked = true;
        $this->invoice_number = $sequence->next_number;
        $this->numberSequence()->associate($sequence);

        $this->save();

        // Increment a sequence, so the next invoice gets new a number.
        $sequence->incrementNextNumber();
    }

    /**
     * Add edit lock on the invoice.
     */
    public function preventModifications(): void
    {
        if ($this->draft) {
            throw new RuntimeException("The invoice draft cannot be locked");
        }

        $this->locked = true;
        $this->save();
    }

    /**
     * Remove edit lock from the invoice.
     */
    public function allowModifications(): void
    {
        $this->locked = false;
        $this->save();
    }

    /**
     * Get the invoice currency.
     */
    public function getCurrency(): Currency
    {
        return Currency::of($this->currency);
    }

    /**
     * Get total amount of VAT.
     */
    public function getVatAmount(): ?Money
    {
        if (! $this->vat_enabled) {
            return null;
        }

        if ($this->total_vat_inclusive && $this->total_vat_exclusive) {
            try {
                return $this->total_vat_inclusive->minus($this->total_vat_exclusive);
            } catch (MoneyMismatchException $e) {
                throw new RuntimeException($e->getMessage(), previous: $e);
            }
        }

        return Money::zero($this->currency);
    }

    /**
     * Get the VAT breakdown.
     *
     * @return \Illuminate\Support\Collection<int, VatBreakdownLine>
     */
    public function getVatBreakdown(): Collection
    {
        if (! $this->vat_enabled) {
            return collect();
        }

        return $this->lines
            ->filter(fn (InvoiceLine $line) => $line->vat_rate !== null && $line->total_price_vat_exclusive !== null && $line->total_price_vat_inclusive !== null)
            ->groupBy
            ->vat_rate
            /** @var Collection<int, \App\Models\InvoiceLine> $lines */
            ->map(function (Collection $lines) {
                try {
                    $totalVatInclusive = MoneyUtils::sum($this->currency, ...$lines->map->total_price_vat_inclusive);
                    $totalVatExclusive = MoneyUtils::sum($this->currency, ...$lines->map->total_price_vat_exclusive);

                    return new VatBreakdownLine(
                        rate: BigNumber::of($lines[0]->vat_rate),
                        total: Money::max(Money::zero($this->currency), $totalVatInclusive->minus($totalVatExclusive)),
                        base: Money::max(Money::zero($this->currency), $totalVatExclusive),
                    );
                } catch (MoneyMismatchException $e) {
                    throw new RuntimeException($e->getMessage(), previous: $e);
                }
            })
            ->values()
            ->sortBy(fn (VatBreakdownLine $line) => $line->rate);
    }

    /**
     * Determine whether the payment is due.
     */
    public function isPaymentDue(): bool
    {
        if ($this->paid) {
            return false;
        }

        return $this->payment_due_to && $this->payment_due_to->isPast();
    }

    /**
     * Determine whether invoice is partially paid.
     */
    public function isPartiallyPaid(): bool
    {
        return $this->remaining_to_pay && $this->total_to_pay && !$this->remaining_to_pay->isZero() && !$this->total_to_pay->isEqualTo($this->remaining_to_pay);
    }

    /**
     * Calculate final amount of the invoice which needs to be paid.
     */
    protected function calculateTotalToPay(): ?Money
    {
        if (! $this->vat_enabled) {
            return $this->total_vat_exclusive;
        }

        if ($this->vat_reverse_charge) {
            return $this->total_vat_exclusive;
        }

        return $this->total_vat_inclusive;
    }

    /**
     * Calculate amount which is remaining to be paid.
     */
    protected function calculateRemainingToPay(): ?Money
    {
        if ($amount = $this->calculateTotalToPay()) {
            $paid = MoneyUtils::sum($this->currency, ...$this->payments->map->amount);

            return Money::max(Money::zero($this->currency), $amount->minus($paid));
        }

        return null;
    }

    /**
     * Get a Pay By Square Pay configuration.
     */
    public function getPayBySquare(): ?Pay
    {
        if (($amount = $this->total_to_pay) && ($iban = $this->supplier->bank_account_iban)) {
            return new Pay(
                payments: [
                    new PendingPayment(
                        paymentOptions: PaymentOption::PAYMENT_ORDER,
                        amount: $amount->getAmount()->toFloat(),
                        currencyCode: $amount->getCurrency()->getCurrencyCode(),
                        bankAccounts: [
                            new BankAccount(
                                iban: Str::replace(' ', '', $iban),
                                bic: $this->supplier->bank_bic,
                            )
                        ],
                        // TODO: urobit nastavitelne
                        // paymentDueDate: $this->payment_due_to?->format('Y-m-d'),
                        variableSymbol: $this->variable_symbol,
                        constantSymbol: $this->constant_symbol,
                        specificSymbol: $this->specific_symbol,
                        // TODO: Bug v bysqr
                        // paymentNote: $this->public_invoice_number ? "Uhrada FA {$this->public_invoice_number}" : null,
                    )
                ]
            );
        }

        return null;
    }

    /**
     * Generate a file name for the invoice.
     */
    public function createFileName(string $locale, string $extension): string
    {
        $prefix = match ($locale) {
            'sk' => 'faktura',
            'de' => 'rechnung',
            default => 'invoice',
        };

        return Str::replace(' ', '_', $prefix.'_'.Str::snake($this->public_invoice_number)).'.'.$extension;
    }

    /**
     * Render invoice to PDF.
     */
    public function renderToPdf(string $locale, string $moneyFormattingLocale): string
    {
        $template = $this->template;

        $options = new SerializerOptions(
            locale: $this->template->resolveLocale($locale),
            moneyFormattingLocale: $moneyFormattingLocale,
        );

        $content = (new InvoiceSerializer)->serialize($this, $options);

        return $template->render($content);
    }

    /**
     * Send an invoice to given email address.
     */
    public function send(string $email, string $message, ?string $locale = null): void
    {
        $mail = new SendInvoiceMail(
            invoice: $this,
            message: $message,
            invoiceLocale: $locale ?: $this->account->getPreferredDocumentLocale(),
            moneyFormattingLocale: $this->account->getMoneyFormattingLocale(),
        );

        Mail::to($email)->send($mail);
    }

    /**
     * Create copy of the invoice.
     */
    public function duplicate(bool $withLines = true): static
    {
        $supplierAddress = $this->supplier->address?->replicate();
        $supplierAddress?->save();

        $supplier = $this->supplier->replicate();
        $supplier->address()->associate($supplierAddress);
        $supplier->save();

        $customerAddress = $this->customer->address?->replicate();
        $customerAddress?->save();

        $customer = $this->customer->replicate();
        $customer->address()->associate($customerAddress);
        $customer->save();

        $signature = $this->signature?->replicate();
        $signature?->save();

        $logo = $this->logo?->replicate();
        $logo?->save();

        $invoice = $this->replicate([
            'uuid', 'public_invoice_number', 'invoice_number', 'variable_symbol',
            'specific_symbol', 'constant_symbol', 'total_vat_inclusive', 'total_vat_exclusive',
        ]);
        $invoice->numberSequence()->dissociate();
        $invoice->logo()->associate($logo);
        $invoice->signature()->associate($signature);
        $invoice->supplier()->associate($supplier);
        $invoice->customer()->associate($customer);
        $invoice->draft = true;
        $invoice->sent = false;
        $invoice->paid = false;
        $invoice->locked = false;
        $invoice->issued_at = now();
        $invoice->supplied_at = now();
        $invoice->payment_due_to = now()->addDays($this->account->invoice_due_days - 1);

        $invoice->save();

        if ($withLines) {
            $this->lines->each(function (InvoiceLine $line) use ($invoice) {
                $copy = $line->replicate(['uuid']);
                $copy->invoice()->associate($invoice);
                $copy->save();
            });
        }

        return $invoice;
    }

    /**
     * Execute given callback while locking the invoice.
     *
     * @template TReturn
     *
     * @param (callable(\App\Models\Invoice): (TReturn)) $callback
     * @param int $for
     * @param int $block
     *
     * @return TReturn
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException
     */
    public function whileLocked(callable $callback, int $for = 10, int $block = 5)
    {
        return Cache::lock('Invoice'.$this->id, $for)->block($block, fn () => $callback($this));
    }

    /**
     * Add a payment to the invoice.
     */
    public function addPayment(Money $amount, PaymentMethod $method, Carbon $receivedAt, ?User $recordedBy = null): Payment
    {
        /** @var \App\Models\Payment $payment */
        $payment = $this->payments()->make([
            'amount' => $amount,
            'method' => $method,
            'received_at' => $receivedAt,
        ]);
        $payment->recordedBy()->associate($recordedBy);
        $payment->account()->associate($this->account);

        $payment->save();

        $this->load('payments');

        $this->calculateTotals();

        return $payment;
    }
}
