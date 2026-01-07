<template>
  <Head :title="draft ? 'Nový koncept' : (publicInvoiceNumber ? `Faktúra ${publicInvoiceNumber}` : 'Faktúra')" />

  <AppLayout class="pb-16">
    <InvoiceFormRoot :context="context">
      <div class="px-4 mt-6">
        <Tabs default-value="general">
          <TabsList>
            <TabsTrigger value="general">Základné informácie</TabsTrigger>
            <TabsTrigger value="payments" :disabled="draft">Úhrady</TabsTrigger>
          </TabsList>
          <TabsContent value="general">
            <div class="flex flex-row justify-between items-center border-b py-4 mb-4">
              <div class="">
                <p class="text-2xl font-medium text-muted-foreground" v-if="draft">Nová faktúra</p>
                <div class="inline-flex items-center gap-2" v-else>
                  <p class="text-2xl font-medium">Faktúra {{ publicInvoiceNumber }}</p>

                  <TooltipProvider :delay-duration="0">
                    <Tooltip v-if="locked">
                      <TooltipTrigger>
                        <LockIcon class="size-4 text-yellow-400" />
                      </TooltipTrigger>
                      <TooltipContent>Modifikácie sú zakázané</TooltipContent>
                    </Tooltip>

                    <Tooltip v-else>
                      <TooltipTrigger>
                        <LockOpenIcon class="size-4 text-destructive" />
                      </TooltipTrigger>
                      <TooltipContent>Modifikácie sú povolené</TooltipContent>
                    </Tooltip>
                  </TooltipProvider>

                  <Badge v-if="sent" variant="secondary"><SendIcon /> Odoslaná </Badge>
                  <Badge v-if="paid" variant="positive"><CheckIcon /> Uhradená </Badge>

                  <Badge v-if="sent && !paid && isPaymentDue" variant="destructive"><CalendarClockIcon /> {{ isPartiallyPaid ? 'Čiastočne uhradená' : 'Po splatnosti' }}</Badge>
                  <Badge v-else-if="sent && !paid && !isPaymentDue && isPartiallyPaid" variant="warning"><CalendarClockIcon /> Čiastočne úhradená</Badge>
                </div>
              </div>

              <div class="flex gap-2">
                <template v-if="draft">
                  <Button @click="confirmDestroyDraft" variant="ghost" size="sm">Zahodiť koncept</Button>
                  <Button :processing="isSaving" @click="save" variant="outline" size="sm" label="Uložiť koncept" :icon="SaveIcon" />
                  <Button :processing="isIssuing" @click="issueInvoice" size="sm" label="Vystaviť" :icon="ClipboardCheckIcon" />
                </template>

                <template v-else>
                  <template v-if="locked">
                    <div v-if="templateLocales.length > 1" class="inline-flex items-center">
                      <Button class="rounded-r-none" as="a" :href="route('invoices.download', id)" size="sm" label="Stiahnuť" :icon="FileDownIcon" />
                      <div class="h-full w-px bg-primary/80"></div>
                      <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                          <Button size="sm" :icon="LanguagesIcon" class="px-2 rounded-l-none" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="min-w-48" align="end">
                          <DropdownMenuLabel>Stiahnuť v jazyku</DropdownMenuLabel>
                          <DropdownMenuItem
                            v-for="locale in templateLocales"
                            as="a"
                            target="_blank"
                            :href="route('invoices.download', { invoice: id, _query: { locale: locale.value } })"
                          >{{ locale.label }}</DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>

                    <Button v-else as="a" :href="route('invoices.download', id)" size="sm" label="Stiahnuť" :icon="FileDownIcon" />

                    <!-- TODO: Pridať support pre email -->
                    <!--<div v-if="! sent" class="inline-flex items-center">-->
                    <!--  <Button @click="sendDialog.activate" class="rounded-r-none border-r-0" variant="outline" size="sm" label="Odoslať" :icon="SendIcon" />-->
                    <!--  <div class="h-full w-px bg-border"></div>-->
                    <!--  <DropdownMenu>-->
                    <!--    <DropdownMenuTrigger as-child>-->
                    <!--      <Button size="sm" :icon="ChevronDownIcon" class="px-2 border-l-0 rounded-l-none" variant="outline" />-->
                    <!--    </DropdownMenuTrigger>-->
                    <!--    <DropdownMenuContent class="min-w-48" align="end">-->
                    <!--      <DropdownMenuItem @select="confirmMarkAsSent">Označiť ako odoslanú</DropdownMenuItem>-->
                    <!--    </DropdownMenuContent>-->
                    <!--  </DropdownMenu>-->
                    <!--</div>-->
                    <Button v-if="!sent" @click="confirmMarkAsSent" variant="outline" size="sm" label="Označiť ako odoslanú" :icon="SendIcon" />

                    <Button v-if="!paid" @click="showAddPaymentDialog" variant="outline" size="sm" label="Pridať úhradu" :icon="BanknoteIcon" />

                    <DropdownMenu>
                      <DropdownMenuTrigger as-child>
                        <Button class="px-2" size="sm" variant="outline" :icon="EllipsisIcon" />
                      </DropdownMenuTrigger>
                      <DropdownMenuContent class="min-w-48" align="end">
                        <DropdownMenuItem @select="confirmDuplicate"><FilesIcon /> Duplikovať</DropdownMenuItem>
                        <DropdownMenuItem @select="unlockInvoice"><LockOpenIcon /> Odomknúť úpravy</DropdownMenuItem>

                        <DropdownMenuSeparator />

                        <DropdownMenuLabel>Platba</DropdownMenuLabel>
                        <DropdownMenuItem @select="showAddPaymentDialog"><BanknoteIcon /> Pridať úhradu</DropdownMenuItem>

                        <DropdownMenuSeparator />

                        <DropdownMenuLabel>Odoslať</DropdownMenuLabel>
                        <!--<DropdownMenuItem @select="sendDialog.activate"><SendIcon /> Odoslať cez e-mail</DropdownMenuItem>-->
                        <DropdownMenuItem v-if="sent" @select="confirmMarkAsNotSent"><MailXIcon /> Označiť ako neodoslanú</DropdownMenuItem>
                        <DropdownMenuItem v-else @select="confirmMarkAsSent"><MailCheckIcon /> Označiť ako odoslanú</DropdownMenuItem>

                        <DropdownMenuSeparator />

                        <DropdownMenuItem @select="confirmDestroy" variant="destructive"><Trash2Icon /> Odstrániť</DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </template>
                  <template v-else>
                    <Button :disabled="!form.isDirty" :processing="isSaving" @click="save" variant="outline" size="sm" label="Uložiť zmeny" :icon="SaveIcon" />

                    <Button :processing="lockInvoiceForm.processing" @click="lockInvoice" size="sm" label="Zamknúť úpravy" :icon="KeySquareIcon" />
                  </template>
                </template>
              </div>
            </div>

            <div class="space-y-12 mt-4">
              <div class="grid grid-cols-2 gap-8">
                <InvoiceFormSectionNumbering />

                <InvoiceFormSectionDates />
              </div>

              <div class="grid grid-cols-2 gap-8">
                <InvoiceFormSectionSupplier />

                <InvoiceFormSectionCustomer />
              </div>

              <InvoiceFormSectionLines />

              <div class="grid grid-cols-2 gap-8">
                <InvoiceFormSectionSettings />

                <InvoiceFormSectionPayment />
              </div>
            </div>
          </TabsContent>
          <TabsContent value="payments">
            <DataTable
              :table="payments"
              empty-table-message="Žiadne úhrady"
              empty-table-description="Zatiaľ neboli zaznamenané žiadne úhrady."
            >
              <template #empty-table>
                <Button @click="showAddPaymentDialog" variant="outline" size="sm" label="Pridať úhradu" class="mt-4" :icon="BanknoteIcon" />
              </template>
            </DataTable>
          </TabsContent>
        </Tabs>

      </div>

      <SendInvoiceDialog
        :id="id"
        :email="customer.email || undefined"
        :message="mailMessage || undefined"
        :control="sendDialog"
      />

      <AddPaymentDialog
        :id="id"
        :amount="remainingToPay || 0"
        :payment-methods="paymentMethods"
        :default-payment-method="paymentMethod"
        :control="paymentDialog"
      />
    </InvoiceFormRoot>
  </AppLayout>
</template>

<script setup lang="ts">
import { Badge } from "@/Components/Badge";
import { useConfirmable } from "@/Components/ConfirmationDialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/Tabs'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/Components/Tooltip";
import { asyncRouter, useToggle } from "@stacktrace/ui";
import InvoiceFormSectionCustomer from "./Form/InvoiceFormSectionCustomer.vue";
import InvoiceFormSectionDates from "./Form/InvoiceFormSectionDates.vue";
import InvoiceFormSectionLines from "./Form/InvoiceFormSectionLines.vue";
import InvoiceFormSectionNumbering from "./Form/InvoiceFormSectionNumbering.vue";
import InvoiceFormSectionPayment from "./Form/InvoiceFormSectionPayment.vue";
import InvoiceFormSectionSettings from "./Form/InvoiceFormSectionSettings.vue";
import InvoiceFormSectionSupplier from "./Form/InvoiceFormSectionSupplier.vue";
import InvoiceFormRoot from "./Form/InvoiceFormRoot.vue";
import SendInvoiceDialog from './Dialogs/SendInvoiceDialog.vue'
import AddPaymentDialog from './Dialogs/AddPaymentDialog.vue'
import type { InvoiceDetailProps } from ".";
import { useInvoiceForm } from './Form'
import { Button } from "@/Components/Button"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger
} from "@/Components/DropdownMenu";
import AppLayout from "@/Layouts/AppLayout.vue"
import { notifyAboutFirstVisibleError, useSaveShortcut } from "@/Utils";
import { Head, router, useForm } from "@inertiajs/vue3"
import { LanguagesIcon, CalendarClockIcon, FilesIcon, Trash2Icon, MailCheckIcon, MailXIcon, CheckIcon, SaveIcon, SendIcon, FileDownIcon, EllipsisIcon, LockIcon, LockOpenIcon, KeySquareIcon, BanknoteIcon, ClipboardCheckIcon } from "lucide-vue-next"
import { computed, ref } from "vue"
import { toast } from "vue-sonner"
import { DataTable } from '@/Components/DataTable'

const props = defineProps<InvoiceDetailProps>()

const context = useInvoiceForm(computed(() => props))
const { form } = context

const isSaving = ref(false)
const isIssuing = ref(false)

const save = () => {
  if (props.locked) {
    return
  }

  isSaving.value = true

  form.patch(route("invoices.update", props.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Zmeny boli uložené.")
    },
    onFinish: () => {
      isSaving.value = false
    },
    onError: () => {
      notifyAboutFirstVisibleError('Niektoré polia sa nepodarilo uložiť.')
    },
  })
}

const issueInvoice = () => {
  isIssuing.value = true

  form.post(route("invoices.issue", props.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Faktúra bola vystavená")
      form.reset()
    },
    onFinish: () => {
      isIssuing.value = false
    },
    onError: () => {
      notifyAboutFirstVisibleError('Faktúru sa nepodarilo vystaviť.')
    },
  })
}

const lockInvoiceForm = useForm({})
const lockInvoice = () => {
  lockInvoiceForm.post(route("invoices.lock.store", props.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success("Faktúra bola uzamknutá")
      },
    },
  )
}

const unlockInvoice = () => {
  router.delete(route("invoices.lock.destroy", props.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Faktúra bola odomknutá")
    },
  })
}

useSaveShortcut(() => save())

const { confirm } = useConfirmable()

const confirmDestroyDraft = () => confirm('Naozaj chcete odstrániť tento koncept?', async () => {
  await asyncRouter.delete(route('invoices.destroy', props.id))
}, { destructive: true, cancelLabel: 'Ponechať', confirmLabel: 'Zahodiť', title: 'Zahodiť koncept' })

const confirmDestroy = () => confirm('Naozaj chcete odstrániť túto faktúru? Vystavené faktúry by nemali byť odstránené. Po odstránení skontrolujte číslovanie faktúr aby nevznikli medzery.', async () => {
  await asyncRouter.delete(route('invoices.destroy', props.id))
}, { destructive: true, cancelLabel: 'Ponechať', confirmLabel: 'Odstrániť', title: 'Odstrániť faktúru' })

const sendDialog = useToggle()
const confirmMarkAsSent = () => confirm('Naozaj chcete túto faktúru označiť ako odoslanú?', async () => {
  await asyncRouter.post(route('invoices.sent-flag.store', props.id), {}, { preserveScroll: true })
}, { title: 'Označiť ako odoslanú' })
const confirmMarkAsNotSent = () => confirm('Naozaj chcete túto faktúro uznačiť ako neodoslanú?', async () => {
  await asyncRouter.delete(route('invoices.sent-flag.destroy', props.id), { preserveScroll: true })
}, { destructive: true,  title: 'Označiť ako neodoslanú' })

const confirmDuplicate = () => confirm('Chcete vytvoriť kópiu tejto faktúry?', async () => {
  await asyncRouter.post(route('invoices.duplicate', props.id))
}, { title: 'Vytvoriť kópiu' })

const paymentDialog = useToggle()
const showAddPaymentDialog = () => {
  paymentDialog.activate()
}
</script>
