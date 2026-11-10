import type { Component } from 'vue'
import {
  Activity,
  Apple,
  Armchair,
  Baby,
  Backpack,
  BedDouble,
  Bike,
  BookOpen,
  Boxes,
  BriefcaseBusiness,
  Camera,
  Car,
  CookingPot,
  Dumbbell,
  Flower2,
  Footprints,
  Gamepad2,
  Gem,
  GraduationCap,
  Headphones,
  HeartPulse,
  House,
  LampDesk,
  Laptop,
  Package,
  PawPrint,
  Pencil,
  Pill,
  Plane,
  Refrigerator,
  Shirt,
  ShoppingBasket,
  Sofa,
  Sparkles,
  Smartphone,
  Tag,
  TentTree,
  Tractor,
  Utensils,
  Watch,
  Wrench
} from 'lucide-vue-next'

/**
 * Stable category slugs mapped to the icon that represents their product type.
 * Keep this independent from display names so it works in every storefront locale.
 */
export const categoryIconBySlug: Record<string, Component> = {
  electronics: Smartphone,
  computers: Laptop,
  laptops: Laptop,
  desktops: Laptop,
  phones: Smartphone,
  smartphones: Smartphone,
  tablets: Smartphone,
  cameras: Camera,
  audio: Headphones,
  gaming: Gamepad2,
  fashion: Shirt,
  clothing: Shirt,
  apparel: Shirt,
  shoes: Footprints,
  footwear: Footprints,
  beauty: Flower2,
  accessories: Watch,
  home: House,
  sports: Dumbbell,
  books: BookOpen,
  toys: Gamepad2,
  groceries: ShoppingBasket,
  food: Utensils,
  office: BriefcaseBusiness,
  bedding: BedDouble,
  fitness: Activity,
  outdoor: TentTree,
  wellness: HeartPulse,
  health: HeartPulse,
  lighting: LampDesk,
  kitchen: CookingPot,
  stationery: Pencil,
  travel: Plane,
  storage: Boxes,
  furniture: Armchair,
  automotive: Car,
  'baby-products': Baby,
  'pet-supplies': PawPrint,
  education: GraduationCap,
  tools: Wrench,
  agriculture: Tractor,
  jewelry: Gem,
  bags: Backpack,
  appliances: Refrigerator,
  pharmacy: Pill,
  produce: Apple,
  decor: Sofa,
  gifts: Sparkles,
  'car-accessories': Package
}

/** Approved icon metadata identifiers and their Lucide components. */
export const categoryIconById: Record<string, Component> = {
  smartphone: Smartphone,
  laptop: Laptop,
  camera: Camera,
  shirt: Shirt,
  footprints: Footprints,
  flower2: Flower2,
  watch: Watch,
  house: House,
  dumbbell: Dumbbell,
  bookopen: BookOpen,
  gamepad2: Gamepad2,
  shoppingbasket: ShoppingBasket,
  briefcasebusiness: BriefcaseBusiness,
  beddouble: BedDouble,
  activity: Activity,
  tenttree: TentTree,
  heartpulse: HeartPulse,
  lampdesk: LampDesk,
  cookingpot: CookingPot,
  pencil: Pencil,
  plane: Plane,
  boxes: Boxes,
  armchair: Armchair,
  car: Car,
  baby: Baby,
  pawprint: PawPrint,
  utensils: Utensils,
  graduationcap: GraduationCap,
  wrench: Wrench,
  tractor: Tractor,
  headphones: Headphones,
  sparkles: Sparkles,
  sofa: Sofa,
  pill: Pill,
  apple: Apple,
  refrigerator: Refrigerator,
  gem: Gem,
  backpack: Backpack,
  package: Package,
  bike: Bike,
  tag: Tag
}

export const categoryIconOptions = [
  ['smartphone', 'Smartphone'], ['laptop', 'Laptop'], ['camera', 'Camera'],
  ['headphones', 'Headphones'], ['gamepad2', 'Gaming'], ['shirt', 'Fashion'],
  ['footprints', 'Shoes'], ['flower2', 'Beauty'], ['watch', 'Accessories'],
  ['house', 'Home'], ['armchair', 'Furniture'], ['sofa', 'Décor'],
  ['dumbbell', 'Sports'], ['activity', 'Fitness'], ['tenttree', 'Outdoor'],
  ['heartpulse', 'Wellness'], ['bookopen', 'Books'], ['graduationcap', 'Education'],
  ['pencil', 'Stationery'], ['briefcasebusiness', 'Office'],
  ['shoppingbasket', 'Groceries'], ['utensils', 'Food'], ['cookingpot', 'Kitchen'],
  ['refrigerator', 'Appliances'], ['beddouble', 'Bedding'], ['lampdesk', 'Lighting'],
  ['boxes', 'Storage'], ['plane', 'Travel'], ['backpack', 'Bags'], ['car', 'Automotive'],
  ['baby', 'Baby'], ['pawprint', 'Pets'], ['pill', 'Health'], ['wrench', 'Tools'],
  ['tractor', 'Agriculture'], ['gem', 'Jewelry'], ['sparkles', 'Gifts'], ['tag', 'Other']
] as const

export interface CategoryIconSource {
  slug: string
  /** Optional API metadata for custom categories; values are limited to the safe registry above. */
  icon?: string | null
  parent?: CategoryIconSource | null
}

function normalizeIconKey(value: string): string {
  return value.trim().toLowerCase().replace(/[\s_-]/g, '')
}

/**
 * Resolve a category icon without relying on translated category names. Explicit
 * admin metadata wins, then an exact slug match, then the nearest parent icon.
 */
export function getCategoryIcon(category: CategoryIconSource, parent?: CategoryIconSource | null): Component {
  const seen = new Set<CategoryIconSource>()

  const resolve = (current: CategoryIconSource | null | undefined): Component | undefined => {
    if (!current || seen.has(current)) return undefined
    seen.add(current)

    const metadataIcon = current.icon ? categoryIconById[normalizeIconKey(current.icon)] : undefined
    if (metadataIcon) return metadataIcon

    const slugIcon = categoryIconBySlug[current.slug.trim().toLowerCase()]
    if (slugIcon) return slugIcon

    return resolve(current.parent)
  }

  return resolve(category) ?? resolve(parent) ?? Tag
}
