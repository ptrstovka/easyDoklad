<template>
  <Dialog :control="control">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Pridať úhradu</DialogTitle>
        <DialogDescription>Faktúra bude označená ako uhradená ak celková suma zaznamenaných úhrad bude rovná sume faktúry.</DialogDescription>
      </DialogHeader>
      <div class="flex flex-col gap-6 pb-4">
        <FormControl label="Suma" :error="form.errors.amount">
          <MoneyInput v-model="form.amount" />
        </FormControl>

        <div class="grid grid-cols-2 gap-4">
          <FormControl label="Spôsob platby" :error="form.errors.method">
            <FormSelect :options="paymentMethods" v-model="form.method" />
          </FormControl>

          <FormControl label="Dátum úhrady" :error="form.errors.received_at">
            <DatePicker v-model="form.received_at" />
          </FormControl>
        </div>
      </div>
      <DialogFooter>
        <Button @click="control.deactivate" variant="outline">Zrušiť</Button>
        <Button :processing="form.processing" @click="save">Pridať úhradu</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { Button } from '@/Components/Button'
import { DatePicker } from '@/Components/DatePicker'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle
} from '@/Components/Dialog'
import { FormControl, FormSelect } from '@/Components/Form'
import { MoneyInput } from '@/Components/MoneyInput'
import { formatDate } from '@vueuse/core'
import { useForm } from '@inertiajs/vue3'
import { onActivated, type SelectOption, type Toggle } from '@stacktrace/ui'

const props = defineProps<{
  id: string
  control: Toggle
  paymentMethods: Array<SelectOption>
  amount: number
  defaultPaymentMethod: string | null
}>()

const form = useForm(() => ({
  amount: props.amount,
  method: props.defaultPaymentMethod || (props.paymentMethods.length > 0 ? props.paymentMethods[0].value : undefined),
  received_at: formatDate(new Date(), 'YYYY-MM-DD'),
}))

const save = () => {
  form.post(route('invoices.payments.store', props.id), {
    onSuccess: () => {
      props.control.deactivate()
    }
  })
}

onActivated(props.control, () => {
  form.reset()
  form.clearErrors()
})
</script>
