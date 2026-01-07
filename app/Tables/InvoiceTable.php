<?php


namespace App\Tables;


use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Support\MoneyUtils;
use App\Tables\Actions\DiscardInvoiceDraftAction;
use App\Tables\Actions\DuplicateInvoiceAction;
use App\Tables\Actions\MarkInvoiceAsSentAction;
use Brick\Money\Currency;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use StackTrace\Ui\DateRange;
use StackTrace\Ui\Link;
use StackTrace\Ui\NumberValue;
use StackTrace\Ui\SelectOption;
use StackTrace\Ui\Table;
use StackTrace\Ui\Table\Actions;
use StackTrace\Ui\Table\Columns;
use StackTrace\Ui\Table\Filters;

class InvoiceTable extends Table
{
    public function __construct(
        protected Currency $currency,
        protected bool $vatEnabled,
        protected string $moneyFormattingLocale,
    )
    {
        $this->defaultSorting(function (Builder $builder) {
            $builder
                ->orderByRaw('CASE WHEN draft = 1 THEN 0 ELSE 1 END ASC')
                ->orderByDesc('issued_at')
                ->orderByDesc('invoice_number');
        });

        $this->searchable(function (Builder $builder, string $term) {
            $builder->whereHas('customer', function (Builder $builder) use ($term) {
                $builder->where('business_name', 'like', '%'.$term.'%');
            });
        });

        $this->highlight(function (Invoice $invoice) {
            if ($invoice->draft) {
                return 'muted';
            }

            return null;
        });

        $this->perPageOptions([25, 50, 100, 200]);
    }

    public function source(): Builder
    {
        $builder = $this->source ?: Invoice::query();

        $builder->with([
            'account.company',
            'customer',
            'payments',
        ]);

        return $builder;
    }

    public function columns(): Table\ColumnCollection
    {
        return Table\ColumnCollection::of([
            Columns\Text::make('#', function (Invoice $invoice) {
                return $invoice->public_invoice_number ?: 'koncept';
            })->width(32)->style(function (Table\Style $style, Invoice $invoice) {
                if (! $invoice->public_invoice_number) {
                    $style->colorMuted()->italic();
                } else {
                    $style->fontMedium();
                }
            })->link(fn (Invoice $invoice) => Link::to(route('invoices.show', $invoice))->show(Gate::allows('view', $invoice))),

            Columns\Text::make('Klient', fn (Invoice $invoice) => $invoice->customer->business_name)
                ->link(fn (Invoice $invoice) => Link::to(route('invoices.show', $invoice))->show(Gate::allows('view', $invoice))),

            Columns\Badge::make('Stav', function (Invoice $invoice) {
                if ($invoice->draft) {
                    return 'draft';
                }

                if ($invoice->paid) {
                    return 'paid';
                }

                if (! $invoice->sent) {
                    return 'issued';
                }

                if ($invoice->isPaymentDue()) {
                    return $invoice->isPartiallyPaid() ? 'partiallyUnpaid' : 'unpaid';
                } else {
                    return $invoice->isPartiallyPaid() ? 'partiallyPaid' : 'sent';
                }
            })->label([
                'draft' => 'Koncept',
                'issued' => 'Vystavená',
                'sent' => 'Odoslaná',
                'paid' => 'Uhradená',
                'unpaid' => 'Po splatnosti',
                'partiallyUnpaid' => 'Čiastočne uhradená',
                'partiallyPaid' => 'Čiastočne uhradená',
            ])->variant([
                'draft' => 'outline',
                'issued' => 'secondary',
                'sent' => 'warning',
                'paid' => 'positive',
                'unpaid' => 'destructive',
                'partiallyUnpaid' => 'destructive',
                'partiallyPaid' => 'warning',
            ])->width(32),

            Columns\Date::make('Dodaná', 'supplied_at')
                ->sortable(using: 'supplied_at', named: 'supplied')
                ->alignCenter()
                ->width(32),

            Columns\Date::make('Vystavená', 'issued_at')
                ->sortable(using: 'issued_at', named: 'issued')
                ->alignCenter()
                ->width(32),

            Columns\Date::make('Splatná', 'payment_due_to')
                ->sortable(using: 'payment_due_to', named: 'paymentDue')
                ->alignCenter()
                ->width(32),

            Columns\Text::make(
                $this->vatEnabled ? 'Spolu bez DPH' : 'Spolu',
                fn (Invoice $invoice) => $invoice->total_vat_exclusive?->formatTo($this->moneyFormattingLocale)
            )->numsTabular()->alignRight()->width(40)->sumarize(function (Collection $invoices) {
                /** @var Collection<int, \Brick\Money\Money> $prices */
                $prices = $invoices->map->total_vat_exclusive->filter()->values();

                if ($prices->isNotEmpty()) {
                    return MoneyUtils::sum($prices[0]->getCurrency()->getCurrencyCode(), ...$prices)
                        ->formatTo($this->moneyFormattingLocale);
                }

                return Money::zero($this->currency)->formatTo($this->moneyFormattingLocale);
            })->sortable(using: 'total_vat_exclusive', named: 'total'),
        ]);
    }

    public function actions(): array
    {
        return [
            Actions\Link::make('Zobraziť', fn (Invoice $invoice) => Link::to(route('invoices.show', $invoice)))
                ->can(fn (Invoice $invoice) => Gate::allows('view', $invoice)),

            MarkInvoiceAsSentAction::make()
                ->can(fn (Invoice $invoice) => Gate::allows('update', $invoice) && !$invoice->draft && !$invoice->sent),

            // MarkInvoiceAsPaidAction::make()
            //     ->can(fn (Invoice $invoice) => Gate::allows('update', $invoice) && !$invoice->draft && !$invoice->paid),

            DuplicateInvoiceAction::make()
                ->can(fn (Invoice $invoice) => Gate::allows('view', $invoice) && !$invoice->draft),

            DiscardInvoiceDraftAction::make()
                ->can(fn (Invoice $invoice) => Gate::allows('delete', $invoice) && $invoice->draft),
        ];
    }

    public function filters(): array
    {
        return [
            Filters\Select::make('Vystavenie', 'draft', [
                new SelectOption('Koncept', 'draft'),
                new SelectOption('Vystavená', 'issued'),
            ])->using(function (Builder $builder, array $selection) {
                $values = collect($selection)->map->value;

                $builder->where(function (Builder $builder) use ($values) {
                    if ($values->contains('draft')) {
                        $builder->orWhere('draft', true);
                    }

                    if ($values->contains('issued')) {
                        $builder->orWhere('draft', false);
                    }
                });
            }),

            Filters\Select::make('Odoslanie', 'sent', [
                new SelectOption('Odoslaná', 'sent'),
                new SelectOption('Neodoslaná', 'notSent'),
            ])->using(function (Builder $builder, array $selection) {
                $values = collect($selection)->map->value;

                $builder->where(function (Builder $builder) use ($values) {
                    if ($values->contains('sent')) {
                        $builder->orWhere('sent', true);
                    }

                    if ($values->contains('notSent')) {
                        $builder->orWhere('sent', false);
                    }
                });
            }),

            Filters\Select::make('Úhrada', 'payment', [
                new SelectOption('Uhradená', 'paid'),
                new SelectOption('Čiastočne uhradená', 'partiallyPaid'),
                new SelectOption('Neuhradená', 'unpaid'),
            ])->using(function (Builder $builder, array $selection) {
                $values = collect($selection)->map->value;

                $builder->where(function (Builder $builder) use ($values) {
                    if ($values->contains('paid')) {
                        $builder->orWhere(fn (Builder $builder) => $builder->where('paid', true)->where('remaining_to_pay', 0));
                    }

                    if ($values->contains('unpaid')) {
                        $builder->orWhere(fn (Builder $builder) => $builder->where('paid', false)->whereColumn('remaining_to_pay', '=', 'total_to_pay'));
                    }

                    if ($values->contains('partiallyPaid')) {
                        $builder->orWhere(fn (Builder $builder) => $builder->where('paid', false)->whereColumn('remaining_to_pay', '!=', 'total_to_pay'));
                    }
                });
            }),

            Filters\DateRange::make('Dátum dodania', 'supplied')
                ->using(fn (Builder $builder, DateRange $range) => $range->applyToQuery($builder, 'supplied_at')),

            Filters\DateRange::make('Dátum vystavenia', 'issued')
                ->using(fn (Builder $builder, DateRange $range) => $range->applyToQuery($builder, 'issued_at')),

            Filters\DateRange::make('Dátum splatnosti', 'paymentDueTo')
                ->using(fn (Builder $builder, DateRange $range) => $range->applyToQuery($builder, 'payment_due_to')),

            Filters\Number::make($this->vatEnabled ? 'Suma bez DPH' : 'Suma', 'total')
                ->using(function (Builder $builder, NumberValue $value) {
                    $moneyValue = new NumberValue(
                        operator: $value->operator,
                        value1: is_numeric($value->value1) ? (int) round($value->value1 * 100) : null,
                        value2: is_numeric($value->value2) ? (int) round($value->value2 * 100) : null,
                    );

                    $moneyValue->where($builder, 'total_vat_exclusive');
                }),

            Filters\Enum::make('Spôsob platby', PaymentMethod::class, 'paymentMethod')
                ->using(fn (Builder $builder, array $paymentMethods) => $builder->whereIn('payment_method', $paymentMethods)),

            Filters\Boolean::make('Po splatnosti', 'due')
                ->using(fn (Builder $builder) => $builder->where('paid', false)->where('payment_due_to', '<', now()->startOfDay())),
        ];
    }
}
