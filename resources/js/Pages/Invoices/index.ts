import type { InvoiceLine } from "@/Components/InvoiceLineInput";
import type { SelectOption } from "@stacktrace/ui";

export interface Company {
  businessName: string | null
  businessId: string | null
  vatId: string | null
  euVatId: string | null
  email: string | null
  phoneNumber: string | null
  website: string | null
  additionalInfo: string | null
  addressLineOne: string | null
  addressLineTwo: string | null
  addressLineThree: string | null
  addressCity: string | null
  addressPostalCode: string | null
  addressCountry: string | null
}

export interface InvoiceDetailProps {
  id: string
  draft: boolean
  locked: boolean
  sent: boolean
  paid: boolean
  publicInvoiceNumber: string | null
  supplier: Company
  customer: Company
  bankName: string | null
  bankAddress: string | null
  bankBic: string | null
  bankAccountNumber: string | null
  bankAccountIban: string | null
  issuedAt: string | null
  suppliedAt: string | null
  paymentDueTo: string | null
  vatEnabled: boolean
  template: string
  footerNote: string | null
  issuedBy: string | null
  issuedByEmail: string | null
  issuedByPhoneNumber: string | null
  issuedByWebsite: string | null
  paymentMethod: string
  variableSymbol: string | null
  specificSymbol: string | null
  constantSymbol: string | null
  showPayBySquare: boolean
  vatReverseCharge: boolean
  lines: Array<InvoiceLine>
  logoUrl: string | null
  signatureUrl: string | null
  totalVatInclusive: number | null
  totalVatExclusive: number | null
  vatAmount: number | null
  vatBreakdown: Array<{
    rate: number
    base: number
    total: number
  }>
  isPaymentDue: boolean
  remainingToPay: number | null

  countries: Array<SelectOption>
  templates: Array<SelectOption>
  paymentMethods: Array<SelectOption<"cash" | "bank-transfer">>

  thousandsSeparator: string
  decimalSeparator: string
  quantityPrecision: number
  pricePrecision: number
  defaultVatRate: number
  currency: {
    code: string
    symbol: string
  }
  mailMessage: string | null

  templateLocales: Array<SelectOption>
}
