<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Models\VariantAttributeValue;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Categories, brands, attributes, products (images, variants, EAV links, inventory).
     */
    public function run(): void
    {
        $this->seedCategories();
        $this->seedBrands();
        $this->call(AttributeSeeder::class);
        $this->seedProducts();
    }

    private function seedCategories(): void
    {
        $categories = [
            ['Electronics', 'electronics', 'Phones, wearables, audio, and more.'],
            ['Fashion', 'fashion', 'Apparel and tailoring for every occasion.'],
            ['Shoes', 'shoes', 'Sneakers, runners, and everything on your feet.'],
            ['Beauty', 'beauty', 'Skincare and cosmetics that pull their weight.'],
            ['Accessories', 'accessories', 'Bags, carry, and finishing touches.'],
            ['Home', 'home', 'Lighting, decor, and home comforts.'],
            ['Sports', 'sports', 'Fitness and outdoor essentials.'],
            ['Books', 'books', 'Books and stationery.'],
            ['Toys', 'toys', 'Toys and games for every age.'],
            ['Groceries', 'groceries', 'Pantry and everyday essentials.'],
        ];

        foreach ($categories as $i => [$name, $slug, $description]) {
            Category::create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'is_active' => true,
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function seedBrands(): void
    {
        $brands = [
            ['TechNova', 'technova', 'Cutting-edge consumer electronics engineered for everyday life.'],
            ['CloudStep', 'cloudstep', 'Lightweight footwear engineered for comfort, speed, and style.'],
            ['Urban Threads', 'urban-threads', 'Modern wardrobe staples with a relaxed, tailored sensibility.'],
            ['Elara Beauty', 'elara-beauty', 'Clean, cruelty-free skincare and cosmetics backed by dermatologists.'],
            ['Voltex', 'voltex', 'Performance action cameras and adventure tech.'],
            ['Northpeek', 'northpeek', 'Thoughtful bags and carry goods for work, travel, and everyday.'],
            ['Hearth & Home', 'hearth-home', 'Warm, sculptural pieces for the modern home.'],
            ['Peak Motion', 'peak-motion', 'Sports and movement essentials.'],
            ['Paper & Co', 'paper-co', 'Books and thoughtful stationery.'],
            ['Daily Harvest', 'daily-harvest', 'Everyday grocery staples.'],
        ];

        foreach ($brands as [$name, $slug, $description]) {
            Brand::create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'is_active' => true,
            ]);
        }
    }

    private function seedProducts(): void
    {
        foreach ($this->catalog() as $spec) {
            $this->createProduct($spec);
        }
    }

    private function createProduct(array $spec): void
    {
        $brand = Brand::where('slug', $spec['brand'])->firstOrFail();
        $category = Category::where('slug', $spec['category'])->firstOrFail();
        $shopCode = match ($spec['category']) {
            'electronics', 'home', 'accessories' => 'TECHHUB',
            'fashion', 'shoes' => 'STYLEHSE',
            'beauty' => 'GLOWGO',
            default => 'EKHMER',
        };
        $shop = Shop::where('code', $shopCode)->firstOrFail();

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'shop_id' => $shop->id,
            'name' => $spec['name'],
            'slug' => $spec['slug'],
            'description' => $spec['description'],
            'short_description' => $spec['short_description'],
            'price' => $spec['price'],
            'compare_at_price' => $spec['compare_at_price'] ?? null,
            'sku' => $spec['sku'],
            'is_featured' => $spec['featured'],
            'is_active' => true,
            'rating_avg' => 0,
            'rating_count' => 0,
            'meta_title' => $spec['name'],
            'meta_description' => $spec['short_description'],
            'seo_keywords' => implode(', ', [$brand->name, $category->name, $spec['slug']]),
        ]);

        $cover = null;
        foreach ([1, 2, 3] as $i) {
            $image = ProductImage::create([
                'product_id' => $product->id,
                'image_path' => "images/products/{$spec['slug']}/{$i}.jpg",
                'alt_text' => "{$spec['name']} view {$i}",
                'sort_order' => $i,
                'is_cover' => $i === 1,
            ]);
            if ($i === 1) {
                $cover = $image;
            }
        }

        foreach ($spec['variants'] as $i => $variantSpec) {
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'image_id' => $cover->id,
                'shop_id' => $shop->id,
                'name' => $variantSpec['name'],
                'sku' => $variantSpec['sku'],
                'price' => $variantSpec['price'],
                'compare_at_price' => $variantSpec['compare_at_price'] ?? null,
                'is_default' => $i === 0,
                'is_active' => true,
            ]);

            foreach ($variantSpec['attrs'] as $attributeName => $value) {
                $attributeValue = $this->resolveAttributeValue($attributeName, $value);
                VariantAttributeValue::create([
                    'product_variant_id' => $variant->id,
                    'attribute_value_id' => $attributeValue->id,
                ]);
            }

            $quantity = $variantSpec['quantity'];
            $inventory = Inventory::create([
                'product_variant_id' => $variant->id,
                'shop_id' => $shop->id,
                'quantity' => $quantity,
                'reserved_quantity' => 0,
                'low_stock_threshold' => 5,
                'sold_count' => max(200 - $quantity, 0),
            ]);

            InventoryTransaction::create([
                'inventory_id' => $inventory->id,
                'shop_id' => $shop->id,
                'created_by' => $this->adminId(),
                'type' => 'in',
                'quantity' => $quantity,
                'balance_after' => $quantity,
                'reference' => 'SEED-'.$variantSpec['sku'],
                'note' => 'Opening stock from catalog seeding',
            ]);
        }
    }

    /**
     * Resolve (or create on the fly) an attribute value for a variant attribute.
     */
    private function resolveAttributeValue(string $attributeName, string $value): AttributeValue
    {
        $attribute = Attribute::firstOrCreate(
            ['slug' => strtolower($attributeName)],
            ['name' => $attributeName, 'type' => 'select', 'is_filterable' => true]
        );

        return AttributeValue::firstOrCreate(
            ['attribute_id' => $attribute->id, 'value' => $value],
            ['swatch_color' => $this->swatchFor($value)]
        );
    }

    private function swatchFor(string $value): ?string
    {
        $swatches = [
            'Black' => '#111827',
            'White' => '#FFFFFF',
            'Silver' => '#D1D5DB',
            'Midnight' => '#0F172A',
            'Sand' => '#E7D8C9',
            'Rose' => '#F43F5E',
            'Green' => '#166534',
            'Navy' => '#1E3A8A',
            'Charcoal' => '#374151',
            'Beige' => '#EAD9C8',
            'Ocean' => '#0C4A6E',
            'Tan' => '#B45309',
            'Oak' => '#92400E',
        ];

        return $swatches[$value] ?? null;
    }

    private function adminId(): ?int
    {
        return User::query()->whereIn('role', [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN])->value('id');
    }

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     brand: string,
     *     category: string,
     *     sku: string,
     *     price: float,
     *     compare_at_price: float|null,
     *     featured: bool,
     *     description: string,
     *     short_description: string,
     *     variants: list<array{sku: string, name: string, attrs: array<string, string>, price: float, compare_at_price: float|null, quantity: int}>
     * }>
     */
    private function catalog(): array
    {
        return [
            [
                'slug' => 'aurora-wireless-headphones',
                'name' => 'Aurora Wireless Headphones',
                'brand' => 'technova',
                'category' => 'electronics',
                'sku' => 'AUR-HP-001',
                'price' => 249.99,
                'compare_at_price' => 319.99,
                'featured' => true,
                'description' => 'Immerse yourself in studio-grade sound with the Aurora Wireless Headphones. Active Noise Cancellation, 40-hour battery life, and plush memory foam ear cushions make these perfect for long listening sessions, travel, and deep focus.',
                'short_description' => 'Studio-grade sound with active noise cancellation and a 40-hour battery.',
                'variants' => [
                    ['sku' => 'aurora-black', 'name' => 'Black', 'attrs' => ['Color' => 'Black'], 'price' => 249.99, 'compare_at_price' => 319.99, 'quantity' => 42],
                    ['sku' => 'aurora-white', 'name' => 'White', 'attrs' => ['Color' => 'White'], 'price' => 249.99, 'compare_at_price' => 319.99, 'quantity' => 18],
                    ['sku' => 'aurora-silver', 'name' => 'Silver', 'attrs' => ['Color' => 'Silver'], 'price' => 259.99, 'compare_at_price' => 329.99, 'quantity' => 7],
                ],
            ],
            [
                'slug' => 'pulse-smartwatch-pro',
                'name' => 'Pulse Smartwatch Pro',
                'brand' => 'technova',
                'category' => 'electronics',
                'sku' => 'PLS-SW-002',
                'price' => 329.0,
                'compare_at_price' => 379.0,
                'featured' => true,
                'description' => 'Track every heartbeat with the Pulse Smartwatch Pro. Advanced health sensors, GPS, built-in workout modes, and a bright always-on AMOLED display. Water resistant up to 5 ATM with a 14-day battery.',
                'short_description' => 'Advanced health tracking, GPS, and a bright AMOLED display in a 14-day battery watch.',
                'variants' => [
                    ['sku' => 'pulse-midnight', 'name' => 'Midnight', 'attrs' => ['Color' => 'Midnight'], 'price' => 329.0, 'compare_at_price' => 379.0, 'quantity' => 35],
                    ['sku' => 'pulse-sand', 'name' => 'Sand', 'attrs' => ['Color' => 'Sand'], 'price' => 329.0, 'compare_at_price' => 379.0, 'quantity' => 21],
                    ['sku' => 'pulse-rose', 'name' => 'Rose', 'attrs' => ['Color' => 'Rose'], 'price' => 349.0, 'compare_at_price' => 399.0, 'quantity' => 9],
                ],
            ],
            [
                'slug' => 'vertex-4k-action-camera',
                'name' => 'Vertex 4K Action Camera',
                'brand' => 'voltex',
                'category' => 'electronics',
                'sku' => 'VTX-AC-003',
                'price' => 399.99,
                'compare_at_price' => null,
                'featured' => false,
                'description' => 'Capture every adventure in stunning 4K/60fps with the Vertex Action Camera. HyperSmooth stabilization, 170° wide-angle lens, waterproof shell to 10m, and a rotating touch screen for vlogs.',
                'short_description' => 'Crisp 4K/60fps stabilization-packed action camera that goes anywhere.',
                'variants' => [
                    ['sku' => 'vertex-base', 'name' => 'Default', 'attrs' => [], 'price' => 399.99, 'compare_at_price' => null, 'quantity' => 24],
                ],
            ],
            [
                'slug' => 'aerolite-running-shoes',
                'name' => 'AeroLite Running Shoes',
                'brand' => 'cloudstep',
                'category' => 'shoes',
                'sku' => 'AERO-RS-004',
                'price' => 139.99,
                'compare_at_price' => 169.99,
                'featured' => true,
                'description' => 'Featherlight and springy, the AeroLite running shoes feature a responsive foam midsole and breathable engineered mesh upper. Designed for daily training, tempo runs, and everything in between.',
                'short_description' => 'Featherlight everyday running shoe with a responsive CloudFoam+ midsole.',
                'variants' => [
                    ['sku' => 'aerolite-blk-42', 'name' => 'Black / US 9', 'attrs' => ['Color' => 'Black', 'Size' => 'US 9'], 'price' => 139.99, 'compare_at_price' => 169.99, 'quantity' => 12],
                    ['sku' => 'aerolite-blk-43', 'name' => 'Black / US 10', 'attrs' => ['Color' => 'Black', 'Size' => 'US 10'], 'price' => 139.99, 'compare_at_price' => 169.99, 'quantity' => 8],
                    ['sku' => 'aerolite-blk-44', 'name' => 'Black / US 11', 'attrs' => ['Color' => 'Black', 'Size' => 'US 11'], 'price' => 139.99, 'compare_at_price' => 169.99, 'quantity' => 4],
                    ['sku' => 'aerolite-wh-42', 'name' => 'White / US 9', 'attrs' => ['Color' => 'White', 'Size' => 'US 9'], 'price' => 139.99, 'compare_at_price' => 169.99, 'quantity' => 15],
                    ['sku' => 'aerolite-wh-43', 'name' => 'White / US 10', 'attrs' => ['Color' => 'White', 'Size' => 'US 10'], 'price' => 139.99, 'compare_at_price' => 169.99, 'quantity' => 11],
                    ['sku' => 'aerolite-wh-44', 'name' => 'White / US 11', 'attrs' => ['Color' => 'White', 'Size' => 'US 11'], 'price' => 139.99, 'compare_at_price' => 169.99, 'quantity' => 0],
                ],
            ],
            [
                'slug' => 'stride-court-sneakers',
                'name' => 'Stride Court Sneakers',
                'brand' => 'cloudstep',
                'category' => 'shoes',
                'sku' => 'STR-SN-005',
                'price' => 89.99,
                'compare_at_price' => null,
                'featured' => false,
                'description' => 'A modern take on the classic court silhouette. Premium leather upper, cushioned insole, and a vulcanized sole for everyday comfort and effortless street style.',
                'short_description' => 'A modern court sneaker in premium leather with cushioned comfort.',
                'variants' => [
                    ['sku' => 'stride-w-9', 'name' => 'White / US 9', 'attrs' => ['Color' => 'White', 'Size' => 'US 9'], 'price' => 89.99, 'compare_at_price' => null, 'quantity' => 18],
                    ['sku' => 'stride-w-10', 'name' => 'White / US 10', 'attrs' => ['Color' => 'White', 'Size' => 'US 10'], 'price' => 89.99, 'compare_at_price' => null, 'quantity' => 14],
                    ['sku' => 'stride-g-9', 'name' => 'Green / US 9', 'attrs' => ['Color' => 'Green', 'Size' => 'US 9'], 'price' => 89.99, 'compare_at_price' => null, 'quantity' => 6],
                    ['sku' => 'stride-g-10', 'name' => 'Green / US 10', 'attrs' => ['Color' => 'Green', 'Size' => 'US 10'], 'price' => 89.99, 'compare_at_price' => null, 'quantity' => 2],
                ],
            ],
            [
                'slug' => 'meridian-slim-fit-blazer',
                'name' => 'Meridian Slim-Fit Blazer',
                'brand' => 'urban-threads',
                'category' => 'fashion',
                'sku' => 'MER-BLZ-006',
                'price' => 189.99,
                'compare_at_price' => 239.99,
                'featured' => false,
                'description' => 'Elevate your wardrobe with the Meridian slim-fit blazer. Cut from a breathable wool-linen blend, it drapes cleanly and works from the office to after-hours.',
                'short_description' => 'Slim-fit wool-linen blazer that moves from office to evening.',
                'variants' => [
                    ['sku' => 'meridian-navy-s', 'name' => 'Navy / S', 'attrs' => ['Color' => 'Navy', 'Size' => 'S'], 'price' => 189.99, 'compare_at_price' => 239.99, 'quantity' => 10],
                    ['sku' => 'meridian-navy-m', 'name' => 'Navy / M', 'attrs' => ['Color' => 'Navy', 'Size' => 'M'], 'price' => 189.99, 'compare_at_price' => 239.99, 'quantity' => 16],
                    ['sku' => 'meridian-navy-l', 'name' => 'Navy / L', 'attrs' => ['Color' => 'Navy', 'Size' => 'L'], 'price' => 189.99, 'compare_at_price' => 239.99, 'quantity' => 8],
                    ['sku' => 'meridian-grey-s', 'name' => 'Charcoal / S', 'attrs' => ['Color' => 'Charcoal', 'Size' => 'S'], 'price' => 189.99, 'compare_at_price' => 239.99, 'quantity' => 5],
                    ['sku' => 'meridian-grey-m', 'name' => 'Charcoal / M', 'attrs' => ['Color' => 'Charcoal', 'Size' => 'M'], 'price' => 189.99, 'compare_at_price' => 239.99, 'quantity' => 12],
                    ['sku' => 'meridian-grey-l', 'name' => 'Charcoal / L', 'attrs' => ['Color' => 'Charcoal', 'Size' => 'L'], 'price' => 189.99, 'compare_at_price' => 239.99, 'quantity' => 3],
                ],
            ],
            [
                'slug' => 'nova-linen-shirt',
                'name' => 'Nova Relaxed Linen Shirt',
                'brand' => 'urban-threads',
                'category' => 'fashion',
                'sku' => 'NOV-SH-007',
                'price' => 59.99,
                'compare_at_price' => 75.0,
                'featured' => false,
                'description' => 'An easy, breezy staple. The Nova relaxed linen shirt is garment-washed for softness and styled to be worn tucked or loose. A year-round essential in warmer climates.',
                'short_description' => 'Garment-washed relaxed linen shirt, a year-round warm-weather staple.',
                'variants' => [
                    ['sku' => 'nova-beige-s', 'name' => 'Beige / S', 'attrs' => ['Color' => 'Beige', 'Size' => 'S'], 'price' => 59.99, 'compare_at_price' => 75.0, 'quantity' => 22],
                    ['sku' => 'nova-beige-m', 'name' => 'Beige / M', 'attrs' => ['Color' => 'Beige', 'Size' => 'M'], 'price' => 59.99, 'compare_at_price' => 75.0, 'quantity' => 30],
                    ['sku' => 'nova-beige-l', 'name' => 'Beige / L', 'attrs' => ['Color' => 'Beige', 'Size' => 'L'], 'price' => 59.99, 'compare_at_price' => 75.0, 'quantity' => 12],
                    ['sku' => 'nova-white-m', 'name' => 'White / M', 'attrs' => ['Color' => 'White', 'Size' => 'M'], 'price' => 59.99, 'compare_at_price' => 75.0, 'quantity' => 20],
                    ['sku' => 'nova-white-l', 'name' => 'White / L', 'attrs' => ['Color' => 'White', 'Size' => 'L'], 'price' => 59.99, 'compare_at_price' => 75.0, 'quantity' => 9],
                ],
            ],
            [
                'slug' => 'lumiere-hydra-glow-serum',
                'name' => 'Lumière Hydra Glow Serum',
                'brand' => 'elara-beauty',
                'category' => 'beauty',
                'sku' => 'LUM-SR-008',
                'price' => 42.0,
                'compare_at_price' => null,
                'featured' => false,
                'description' => 'A weightless, deeply hydrating serum featuring hyaluronic acid and vitamin C. Wake up to visibly brighter, plumper skin. Dermatologist-tested and fragrance-free.',
                'short_description' => 'Weightless hydration serum with hyaluronic acid and vitamin C.',
                'variants' => [
                    ['sku' => 'lumiere-30ml', 'name' => '30 ml', 'attrs' => ['Size' => '30 ml'], 'price' => 42.0, 'compare_at_price' => null, 'quantity' => 120],
                ],
            ],
            [
                'slug' => 'velvet-matte-lipstick-ruby',
                'name' => 'Velvet Matte Lipstick — Ruby',
                'brand' => 'elara-beauty',
                'category' => 'beauty',
                'sku' => 'VEL-LP-009',
                'price' => 24.0,
                'compare_at_price' => 30.0,
                'featured' => false,
                'description' => 'A rich, velvety matte lipstick in a classic ruby red. Long-wearing comfort formula enriched with jojoba oil that glides on and stays through coffee and conversation.',
                'short_description' => 'Velvet-matte long-wear lipstick in a classic ruby red.',
                'variants' => [
                    ['sku' => 'ruby-01', 'name' => 'Ruby', 'attrs' => [], 'price' => 24.0, 'compare_at_price' => 30.0, 'quantity' => 200],
                ],
            ],
            [
                'slug' => 'orbit-everyday-backpack',
                'name' => 'Orbit Everyday Backpack',
                'brand' => 'northpeek',
                'category' => 'accessories',
                'sku' => 'ORB-BP-010',
                'price' => 119.99,
                'compare_at_price' => 149.99,
                'featured' => true,
                'description' => 'A waterproof, thoughtfully organized carry for work and weekends. Padded 16" laptop sleeve, external water-bottle pocket, and quick-access magnetic closure.',
                'short_description' => 'Waterproof, organized 24 L everyday backpack with padded 16" laptop sleeve.',
                'variants' => [
                    ['sku' => 'orbit-black', 'name' => 'Black', 'attrs' => ['Color' => 'Black'], 'price' => 119.99, 'compare_at_price' => 149.99, 'quantity' => 44],
                    ['sku' => 'orbit-ocean', 'name' => 'Ocean', 'attrs' => ['Color' => 'Ocean'], 'price' => 119.99, 'compare_at_price' => 149.99, 'quantity' => 15],
                ],
            ],
            [
                'slug' => 'cascade-leather-tote',
                'name' => 'Cascade Leather Tote',
                'brand' => 'northpeek',
                'category' => 'accessories',
                'sku' => 'CAS-TT-011',
                'price' => 219.0,
                'compare_at_price' => null,
                'featured' => false,
                'description' => 'Handcrafted full-grain leather tote with a structured silhouette, interior zip pocket, and key leash. Ages beautifully with use and carries everything, quietly.',
                'short_description' => 'Handcrafted full-grain leather tote that only gets better with age.',
                'variants' => [
                    ['sku' => 'cascade-tan', 'name' => 'Tan', 'attrs' => ['Color' => 'Tan'], 'price' => 219.0, 'compare_at_price' => null, 'quantity' => 14],
                ],
            ],
            [
                'slug' => 'serene-wooden-desk-lamp',
                'name' => 'Serene Wooden Desk Lamp',
                'brand' => 'hearth-home',
                'category' => 'home',
                'sku' => 'SER-DL-012',
                'price' => 79.99,
                'compare_at_price' => 99.99,
                'featured' => false,
                'description' => 'Warm, dimmable LED light on a solid oak base. A sculptural addition to any desk or nightstand, with a minimalist touch dimmer and 3 color temperatures.',
                'short_description' => 'Warm dimmable LED desk lamp on a solid oak base.',
                'variants' => [
                    ['sku' => 'serene-oak', 'name' => 'Oak', 'attrs' => ['Color' => 'Oak'], 'price' => 79.99, 'compare_at_price' => 99.99, 'quantity' => 38],
                ],
            ],
        ];
    }
}
