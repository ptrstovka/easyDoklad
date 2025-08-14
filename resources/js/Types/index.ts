import type { LucideIcon } from 'lucide-vue-next'
import type { Config } from 'ziggy-js'

export interface BreadcrumbItem {
  title: string
  href: string
}

export interface NavItem {
  title: string
  href: string
  icon?: LucideIcon
  isActive?: boolean
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  auth: {
    user: User;
  }
  ziggy: Config & { location: string }
  sidebarOpen: boolean
};

export type Feature = 'expenses'

export interface User {
  name: string
  email: string
  avatar?: string
  emailVerifiedAt: string | null
  accounts: Array<{
    id: number
    name: string
    current: boolean
  }>
  features: Record<Feature, boolean>
}

export type BreadcrumbItemType = BreadcrumbItem
