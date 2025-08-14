<template>
  <div>
    <div class="border-b border-sidebar-border/80">
      <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
        <!-- Mobile Menu -->
        <div class="lg:hidden">
          <Sheet>
            <SheetTrigger :as-child="true">
              <Button variant="ghost" size="icon" class="mr-2 h-9 w-9">
                <MenuIcon class="h-5 w-5"/>
              </Button>
            </SheetTrigger>
            <SheetContent side="left" class="w-[300px] p-6">
              <SheetTitle class="sr-only">Navigation Menu</SheetTitle>
              <SheetHeader class="flex justify-start text-left">
                <AppLogoIcon class="size-6 fill-current text-black dark:text-white"/>
              </SheetHeader>
              <div class="flex h-full flex-1 flex-col justify-between space-y-4 py-6">
                <nav class="-mx-3 space-y-1">
                  <NavigationButton
                    v-for="item in navigation"
                    :item="item"
                    :class="[
                      'flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent',
                      { 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100': item.isActive },
                    ]"
                  >
                    <NavigationButtonIcon class="h-5 w-5" />
                    {{ item.title }}
                  </NavigationButton>
                </nav>
              </div>
            </SheetContent>
          </Sheet>
        </div>

        <Link :href="route('dashboard')" class="flex items-center gap-x-2">
          <AppLogo/>
        </Link>

        <!-- Desktop Menu -->
        <div class="hidden h-full lg:flex lg:flex-1">
          <NavigationMenu class="ml-10 flex h-full items-stretch">
            <NavigationMenuList class="flex h-full items-stretch space-x-2">
              <NavigationMenuItem v-for="item in navigation" class="relative flex h-full items-center">
                <NavigationButton
                  :item="item"
                  :class="[
                    navigationMenuTriggerStyle(),
                    { 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100': item.isActive },
                    'h-9 cursor-pointer px-3'
                  ]">
                  <NavigationButtonIcon class="mr-2 h-4 w-4" />
                  {{ item.title }}
                </NavigationButton>
                <div v-if="item.isActive" class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"></div>
              </NavigationMenuItem>
            </NavigationMenuList>
          </NavigationMenu>
        </div>

        <div class="ml-auto flex items-center">
          <div class="relative flex items-center space-x-1 mr-1">
          <!--  <Button variant="ghost" size="icon" class="group h-9 w-9 cursor-pointer">-->
          <!--    <SearchIcon class="size-5 opacity-80 group-hover:opacity-100"/>-->
          <!--  </Button>-->
            <TooltipProvider :delay-duration="0">
              <Tooltip>
                <TooltipTrigger class="hidden sm:block">
                  <a class="flex" href="mailto:ps@stacktrace.sk"><DevPreview /></a>
                </TooltipTrigger>
                <TooltipContent class="w-56">
                  Aplikácia je vo vývoji a obsahuje chyby. Feedback alebo prípadné problémy môžete reportovať mailom na
                  <a class="underline" href="mailto:ps@stacktrace.sk">ps@stacktrace.sk</a>.
                  PR na <a class="underline" href="https://github.com/ptrstovka/faktury">Githube</a> sú vítané. 😄️
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          </div>

          <DropdownMenu>
            <DropdownMenuTrigger :as-child="true">
              <Button variant="ghost">{{ account.name }}<ChevronDownIcon class="size-4" /></Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
              <UserMenuContent :user="auth.user"/>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </div>

    <div v-if="props.breadcrumbs.length > 1" class="flex w-full border-b border-sidebar-border/70">
      <div class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl">
        <Breadcrumbs :breadcrumbs="breadcrumbs"/>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import AppLogo from '@/Components/AppLogo.vue'
import AppLogoIcon from '@/Components/AppLogoIcon.vue'
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
import { Button } from '@/Components/Button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/Components/DropdownMenu'
import {
  NavigationMenu,
  NavigationMenuItem,
  NavigationMenuList,
  navigationMenuTriggerStyle
} from '@/Components/NavigationMenu'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/Sheet'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/Components/Tooltip";
import UserMenuContent from '@/Components/UserMenuContent.vue'
import type { BreadcrumbItem } from '@/Types'
import { Link, usePage } from '@inertiajs/vue3'
import { useNavigation, NavigationButton, NavigationButtonIcon } from "@stacktrace/ui";
import { MenuIcon, LayoutGridIcon, ChevronDownIcon, FileTextIcon, BanknoteArrowDownIcon, BanknoteArrowUpIcon } from 'lucide-vue-next'
import { computed } from 'vue'
import { DevPreview } from '@/Components/FeatureFlags'

interface Props {
  breadcrumbs?: BreadcrumbItem[]
}

const props = withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
})

const page = usePage()
const auth = computed(() => page.props.auth)

const account = computed(() => page.props.auth.user.accounts.find(it => it.current)!)

const navigation = useNavigation(computed(() => {
  const hasExpensesFeature = auth.value.user.features.expenses

  const menu = [
    {
      title: 'Prehľad',
      action: { route: 'dashboard' },
      icon: LayoutGridIcon,
    },
    {
      title: hasExpensesFeature ? 'Príjmy' : 'Vystavené faktúry',
      action: { route: 'invoices' },
      active: { route: 'invoices*' },
      icon: hasExpensesFeature ? BanknoteArrowUpIcon : FileTextIcon,
    },
  ]

  if (hasExpensesFeature) {
    menu.push({
      title: 'Náklady',
      action: { route: 'expenses' },
      active: { route: 'expenses*' },
      icon: BanknoteArrowDownIcon,
    })
  }

  return menu
}))
</script>
