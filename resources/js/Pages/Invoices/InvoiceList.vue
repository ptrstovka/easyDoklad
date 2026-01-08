<template>
  <Head title="Vystavené faktúry"/>

  <AppLayout class="pb-12">
    <div class="flex flex-row items-end justify-between pt-6 px-4">
      <Heading title="Vystavené faktúry" class="mb-0" />

      <div class="inline-flex flex-row gap-2">
        <Button v-if="! invoices.isEmpty" :processing="draft.processing" @click="createDraft" size="sm" label="Nová faktúra" :icon="PlusIcon" />
      </div>
    </div>

    <div class="px-4">
      <DataTable
        :table="invoices"
        inset-left="pl-1"
        inset-right="pr-1"
        empty-table-message="Žiadne vystavené faktúry"
        empty-table-description="Zatiaľ neboli vystavené žiadne faktúry."
        empty-results-message="Žiadne výsledky"
        empty-results-description="Neboli nájdené žiadne vystavené faktúry."
        @add-payment="addPayment"
      >
        <template #empty-table>
          <Button class="mt-4" :processing="draft.processing" @click="createDraft" size="sm" label="Nová faktúra" :icon="PlusIcon" />
        </template>
      </DataTable>
    </div>

    <AddPaymentDialog
      v-if="addPaymentToInvoice"
      :id="addPaymentToInvoice.id"
      :control="addPaymentToInvoiceDialog"
      :amount="addPaymentToInvoice.remainingToPay || 0"
      :payment-methods="paymentMethods"
      :default-payment-method="addPaymentToInvoice.paymentMethod"
    />
  </AppLayout>
</template>

<script setup lang="ts">
import { type DataTableValue, DataTable } from "@/Components/DataTable";
import Heading from "@/Components/Heading.vue";
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, useForm } from "@inertiajs/vue3";
import { Button } from '@/Components/Button'
import { type SelectOption, useToggle } from '@stacktrace/ui'
import { PlusIcon } from 'lucide-vue-next'
import { nextTick, ref } from 'vue'
import AddPaymentDialog from './Dialogs/AddPaymentDialog.vue'

interface Invoice {
  id: string
  remainingToPay: number | null
  paymentMethod: string
}

const props = defineProps<{
  invoices: DataTableValue<Invoice, number>
  paymentMethods: Array<SelectOption>
}>()

const draft = useForm(() => ({}))
const createDraft = () => draft.post(route('invoices.store'))

const addPaymentToInvoice = ref<Invoice>()
const addPaymentToInvoiceDialog = useToggle()
const addPayment = (selection: Array<number>) => {
  addPaymentToInvoice.value = props.invoices.rows.find(it => selection.includes(it.key))?.resource || undefined

  if (addPaymentToInvoice.value) {
    nextTick(() =>addPaymentToInvoiceDialog.activate())
  }
}
</script>
