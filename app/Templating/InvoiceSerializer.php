<?php


namespace App\Templating;


use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Upload;
use App\Support\VatBreakdownLine;
use Brick\Money\Money;
use Bysqr\Encoder;
use Bysqr\Pay;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;

class InvoiceSerializer
{
    /**
     * Serialize invoice for template renderer.
     */
    public function serialize(Invoice $invoice, SerializerOptions $options): array
    {
        $previousLocale = App::getLocale();
        App::setLocale($options->locale);

        $serialized = [
            'locale' => $options->locale,
            'document' => [
                'public_number' => $invoice->public_invoice_number,

                'issued_at' => $invoice->issued_at ? $this->date($invoice->issued_at) : null,
                'supplied_at' => $invoice->supplied_at ? $this->date($invoice->supplied_at) : null,
                'payment_due_to' => $invoice->payment_due_to ? $this->date($invoice->payment_due_to) : null,

                'supplier' => $this->company($invoice->supplier),
                'customer' => $this->company($invoice->customer),

                'issued_by' => $invoice->issued_by,
                'issued_by_email' => $invoice->issued_by_email,
                'issued_by_phone_number' => $invoice->issued_by_phone_number,
                'issued_by_website' => $invoice->issued_by_website,

                'footer_note' => $invoice->footer_note,

                'payment_method' => [
                    'name' => $invoice->payment_method->name(),
                    'value' => $invoice->payment_method->value,
                ],

                'bank_name' => $invoice->supplier->bank_name,
                'bank_bic' => $invoice->supplier->bank_bic,
                'bank_address' => $invoice->supplier->bank_address,
                'bank_account_number' => $invoice->supplier->bank_account_number,
                'bank_account_iban' => $invoice->supplier->bank_account_iban,

                'variable_symbol' => $invoice->variable_symbol,
                'constant_symbol' => $invoice->constant_symbol,
                'specific_symbol' => $invoice->specific_symbol,

                'vat_enabled' => $invoice->vat_enabled,
                'vat_reverse_charge' => $invoice->vat_reverse_charge,

                'vat_amount' => ($vatAmount = $invoice->getVatAmount()) ? $this->money($vatAmount, $options->moneyFormattingLocale) : null,
                'total_vat_inclusive' => $invoice->total_vat_inclusive ? $this->money($invoice->total_vat_inclusive, $options->moneyFormattingLocale) : null,
                'total_vat_exclusive' => $invoice->total_vat_exclusive ? $this->money($invoice->total_vat_exclusive, $options->moneyFormattingLocale) : null,

                'lines' => $invoice->getSortedLines()->map(fn (InvoiceLine $line) => [
                    'position' => $line->position,
                    'title' => $line->title,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                    'unit_price_vat_exclusive' => $line->unit_price_vat_exclusive ? $this->money($line->unit_price_vat_exclusive, $options->moneyFormattingLocale) : null,
                    'vat_rate' => $line->vat_rate,
                    'vat_amount' => ($vatAmount = $line->getVatAmount()) && $invoice->vat_enabled ? $this->money($vatAmount, $options->moneyFormattingLocale) : null,
                    'total_vat_exclusive' => $line->total_price_vat_exclusive ? $this->money($line->total_price_vat_exclusive, $options->moneyFormattingLocale) : null,
                    'total_vat_inclusive' => $line->total_price_vat_inclusive ? $this->money($line->total_price_vat_inclusive, $options->moneyFormattingLocale) : null,
                ]),

                'vat_breakdown' => $invoice->getVatBreakdown()->map(fn (VatBreakdownLine $line) => [
                    'rate' => $line->rate,
                    'base' => $this->money($line->base, $options->moneyFormattingLocale),
                    'total' => $this->money($line->total, $options->moneyFormattingLocale),
                ]),

                'logo' => $invoice->logo ? $this->image($invoice->logo) : null,
                'signature' => $invoice->signature ? $this->image($invoice->signature) : null,
                'pay_by_square' => $invoice->show_pay_by_square && ($pay = $invoice->getPayBySquare()) ? $this->payBySquare($pay) : null,

                'total_to_pay' => ($toPay = $invoice->total_to_pay) ? $this->money($toPay, $options->moneyFormattingLocale) : null,
            ],
        ];

        App::setLocale($previousLocale);

        return $serialized;
    }

    protected function payBySquare(Pay $pay): string
    {
        return Encoder::make($pay)->toPng();
    }

    protected function image(Upload $file): ?string
    {
        $mime = $file->mime();

        if (! in_array($mime, ['image/png', 'image/jpg', 'image/jpeg'])) {
            return null;
        }

        if ($contents = $file->contents()) {
            return "data:{$mime};base64,".base64_encode($contents);
        }

        return null;
    }

    protected function money(Money $money, string $locale): array
    {
        return [
            'formatted' => $money->formatTo($locale),
            'value' => $money->getMinorAmount()->toInt(),
            'currency' => [
                'name' => $money->getCurrency()->getName(),
                'code' => $money->getCurrency()->getCurrencyCode(),
            ],
        ];
    }

    protected function company(Company $company): array
    {
        return [
            'business_name' => $company->business_name,
            'business_id' => $company->business_id,
            'vat_id' => $company->vat_id,
            'eu_vat_id' => $company->eu_vat_id,
            'address' => [
                'line_one' => $company->address?->line_one,
                'line_two' => $company->address?->line_two,
                'line_three' => $company->address?->line_three,
                'city' => $company->address?->city,
                'postal_code' => $company->address?->postal_code,
                'country' => $company->address?->country ? [
                    'code' => $company->address->country->value,
                    'name' => $company->address->country->name(),
                ] : null,
            ],
            'additional_info' => $company->additional_info,
            'additional_info_lines' => $company->additional_info
                ? collect(explode(PHP_EOL, $company->additional_info))
                    ->map(fn (string $line) => trim($line))
                    ->filter(fn (string $line) => !empty($line))
                    ->values()
                    ->all()
                : [],
            'email' => $company->email,
            'phone_number' => $company->phone_number,
            'website' => $company->website,
        ];
    }

    protected function date(Carbon $date, string $format = 'd.m.Y'): array
    {
        return [
            'formatted' => $date->format($format),
            'value' => $date->toIso8601ZuluString(),
        ];
    }
}
