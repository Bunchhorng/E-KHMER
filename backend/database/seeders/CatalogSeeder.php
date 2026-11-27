<?php

namespace Database\Seeders;

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
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Database\Seeders\Support\CambodianCatalogData;
use Database\Seeders\Support\DemoMediaGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo catalog data may only be seeded in local or testing environments.');

        $this->call(AttributeSeeder::class);

        $categories = $this->seedCategories();
        $brands = $this->seedBrands();
        $shops = Shop::pluck('id', 'code');
        $fallbackShopId = Shop::active()->orderBy('id')->value('id');
        $admin = User::where('email', 'admin@ekhmer.com')->firstOrFail();

        $attributeValues = $this->indexAttributeValues();
        $productsData = CambodianCatalogData::products();

        foreach ($productsData as $index => $item) {
            $sku = 'EKH-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $slug = Str::slug($item['name']);
            $shopId = $shops[$item['shop']] ?? $fallbackShopId;
            $categoryId = $categories[$item['category']];
            $brandId = $brands[$item['brand']];

            $isOnSale = $index % 3 === 0 || $index % 5 === 0;
            $isActive = $index < 245; // Keep 5 inactive drafts for admin catalog visibility tests.
            $isFeatured = $isActive && ($index % 10 === 0);

            $description = '<p><strong>'.$item['name'].'</strong> ('.$item['khmer'].')</p>'
                .'<p>'.$item['summary'].'</p>'
                .'<p>'.$item['origin_note'].'</p>';

            $product = Product::withTrashed()->firstOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'shop_id' => $shopId,
                    'name' => $item['name'],
                    'slug' => $slug,
                    'description' => $description,
                    'short_description' => $item['khmer'].' — '.$item['summary'],
                    'price' => $item['price'],
                    'compare_at_price' => $isOnSale ? round($item['price'] * 1.18, 2) : null,
                    'cost_price' => round($item['price'] * 0.62, 2),
                    'barcode' => '8842026'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                    'weight' => round(0.25 + (($index % 7) * 0.2), 2),
                    'is_featured' => $isFeatured,
                    'is_active' => $isActive,
                    'rating_avg' => 0,
                    'rating_count' => 0,
                    'meta_title' => $item['name'].' | E-KHMER',
                    'meta_description' => $item['summary'],
                    'seo_keywords' => implode(', ', [$item['name'], $item['khmer'], $item['category'], 'Cambodia']),
                ]
            );

            if (! $product->wasRecentlyCreated) {
                continue;
            }

            $coverUrl = DemoMediaGenerator::productImageUrl($item['category'], $index, $item['name']);
            $coverImage = ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $coverUrl,
                'alt_text' => $item['name'],
                'sort_order' => 1,
                'is_cover' => true,
            ]);

            if ($index % 4 === 0) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => DemoMediaGenerator::productImageUrl($item['category'], $index + 1, $item['name']),
                    'alt_text' => $item['name'].' alternate view',
                    'sort_order' => 2,
                    'is_cover' => false,
                ]);
            }

            $variantSpecs = $this->variantsForMode($item['variant_mode'], $item['price'], $index);

            foreach ($variantSpecs as $vIdx => $spec) {
                $variantSku = $sku.'-'.str_pad((string) ($vIdx + 1), 2, '0', STR_PAD_LEFT);
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'image_id' => $coverImage->id,
                    'shop_id' => $shopId,
                    'name' => $spec['label'],
                    'sku' => $variantSku,
                    'barcode' => '8843'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT).str_pad((string) ($vIdx + 1), 5, '0', STR_PAD_LEFT),
                    'price' => $spec['price'],
                    'cost_price' => round($spec['price'] * 0.62, 2),
                    'compare_at_price' => $isOnSale ? round($spec['price'] * 1.18, 2) : null,
                    'is_default' => $vIdx === 0,
                    'is_active' => true,
                ]);

                foreach ($spec['attributes'] as $attrSlug => $attrValue) {
                    $valueId = $attributeValues[$attrSlug][$attrValue] ?? null;
                    if ($valueId !== null) {
                        VariantAttributeValue::create([
                            'product_variant_id' => $variant->id,
                            'attribute_value_id' => $valueId,
                        ]);
                    }
                }

                $quantity = $this->initialStockForIndex($index, $vIdx);
                $inventory = Inventory::create([
                    'product_variant_id' => $variant->id,
                    'shop_id' => $shopId,
                    'quantity' => $quantity,
                    'reserved_quantity' => 0,
                    'low_stock_threshold' => 5,
                    'sold_count' => 0,
                ]);

                if ($quantity > 0) {
                    InventoryTransaction::create([
                        'inventory_id' => $inventory->id,
                        'shop_id' => $shopId,
                        'created_by' => $admin->id,
                        'type' => 'in',
                        'quantity' => $quantity,
                        'balance_after' => $quantity,
                        'reference' => 'SEED-'.$variantSku,
                        'note' => 'Initial demo stock allocation.',
                    ]);
                }
            }
        }
    }

    /** @return array<string, int> */
    private function seedCategories(): array
    {
        $ids = [];
        foreach (CambodianCatalogData::categories() as $index => $cat) {
            $model = Category::firstOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name' => $cat['name'],
                    'icon' => $cat['icon'],
                    'description' => $cat['description'],
                    'image' => DemoMediaGenerator::categoryImageUrl($cat['slug']),
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
            $ids[$cat['slug']] = $model->id;
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function seedBrands(): array
    {
        $ids = [];
        foreach (CambodianCatalogData::brands() as $brand) {
            $model = Brand::firstOrCreate(
                ['slug' => $brand['slug']],
                [
                    'name' => $brand['name'],
                    'description' => $brand['description'],
                    'logo' => DemoMediaGenerator::brandLogoUrl($brand['slug'], $brand['name']),
                    'is_active' => true,
                ]
            );
            $ids[$brand['slug']] = $model->id;
        }

        return $ids;
    }

    /** @return array<string, array<string, int>> */
    private function indexAttributeValues(): array
    {
        $map = [];
        $rows = AttributeValue::with('attribute')->get();
        foreach ($rows as $row) {
            $slug = $row->attribute?->slug;
            if ($slug !== null) {
                $map[$slug][$row->value] = $row->id;
            }
        }

        return $map;
    }

    /**
     * @return list<array{label: string, price: float, attributes: array<string, string>}>
     */
    private function variantsForMode(string $mode, float $basePrice, int $productIndex): array
    {
        return match ($mode) {
            'weight' => [
                ['label' => '250g', 'price' => $basePrice, 'attributes' => ['weight-pack' => '250g']],
                ['label' => '500g', 'price' => round($basePrice * 1.85, 2), 'attributes' => ['weight-pack' => '500g']],
            ],
            'rice_weight' => [
                ['label' => '2kg', 'price' => $basePrice, 'attributes' => ['weight-pack' => '2kg']],
                ['label' => '5kg', 'price' => round($basePrice * 2.25, 2), 'attributes' => ['weight-pack' => '5kg']],
            ],
            'spice_weight' => [
                ['label' => '100g / Whole', 'price' => $basePrice, 'attributes' => ['weight-pack' => '100g', 'finish' => 'Whole']],
                ['label' => '250g / Whole', 'price' => round($basePrice * 2.15, 2), 'attributes' => ['weight-pack' => '250g', 'finish' => 'Whole']],
            ],
            'coffee' => [
                ['label' => '250g / Whole Bean', 'price' => $basePrice, 'attributes' => ['weight-pack' => '250g', 'finish' => 'Whole Bean']],
                ['label' => '250g / Ground', 'price' => $basePrice, 'attributes' => ['weight-pack' => '250g', 'finish' => 'Ground']],
                ['label' => '500g / Whole Bean', 'price' => round($basePrice * 1.80, 2), 'attributes' => ['weight-pack' => '500g', 'finish' => 'Whole Bean']],
            ],
            'clothing' => [
                ['label' => 'Golden Saffron / M', 'price' => $basePrice, 'attributes' => ['color' => 'Golden Saffron', 'size' => 'M', 'material' => 'Handloom Silk']],
                ['label' => 'Indigo Blue / L', 'price' => $basePrice, 'attributes' => ['color' => 'Indigo Blue', 'size' => 'L', 'material' => 'Handloom Silk']],
                ['label' => 'Crimson Red / S', 'price' => $basePrice, 'attributes' => ['color' => 'Crimson Red', 'size' => 'S', 'material' => 'Handloom Silk']],
            ],
            'color_material' => [
                ['label' => 'Crimson Red / Free Size', 'price' => $basePrice, 'attributes' => ['color' => 'Crimson Red', 'size' => 'Free Size', 'material' => 'Organic Cotton']],
                ['label' => 'Indigo Blue / Free Size', 'price' => $basePrice, 'attributes' => ['color' => 'Indigo Blue', 'size' => 'Free Size', 'material' => 'Organic Cotton']],
            ],
            'apparel' => $productIndex % 2 === 0
                ? [
                    ['label' => 'Black / M', 'price' => $basePrice, 'attributes' => ['color' => 'Black', 'size' => 'M']],
                    ['label' => 'White / L', 'price' => $basePrice, 'attributes' => ['color' => 'White', 'size' => 'L']],
                ]
                : [
                    ['label' => 'Black / EU 41', 'price' => $basePrice, 'attributes' => ['color' => 'Black', 'size' => 'EU 41']],
                    ['label' => 'White / EU 42', 'price' => $basePrice, 'attributes' => ['color' => 'White', 'size' => 'EU 42']],
                ],
            'tech' => $productIndex % 4 === 0
                ? [
                    ['label' => 'Black / 128GB', 'price' => $basePrice, 'attributes' => ['color' => 'Black', 'storage' => '128GB']],
                    ['label' => 'Silver / 256GB', 'price' => round($basePrice + 45.00, 2), 'attributes' => ['color' => 'Silver', 'storage' => '256GB']],
                ]
                : [
                    ['label' => 'Black / Matte', 'price' => $basePrice, 'attributes' => ['color' => 'Black', 'finish' => 'Matte']],
                    ['label' => 'Silver / Matte', 'price' => $basePrice, 'attributes' => ['color' => 'Silver', 'finish' => 'Matte']],
                ],
            'capacity' => [
                ['label' => '250ml', 'price' => $basePrice, 'attributes' => ['capacity' => '250ml']],
                ['label' => '500ml', 'price' => round($basePrice * 1.65, 2), 'attributes' => ['capacity' => '500ml']],
            ],
            'material_size' => [
                ['label' => 'Natural Bamboo / Standard', 'price' => $basePrice, 'attributes' => ['color' => 'Natural Bamboo', 'material' => 'Woven Bamboo', 'finish' => 'Standard']],
            ],
            'finish_size' => [
                ['label' => 'Natural Bamboo / Matte', 'price' => $basePrice, 'attributes' => ['color' => 'Natural Bamboo', 'finish' => 'Matte']],
            ],
            'sport' => [
                ['label' => 'Forest Green / Standard', 'price' => $basePrice, 'attributes' => ['color' => 'Forest Green', 'finish' => 'Standard']],
                ['label' => 'Black / Standard', 'price' => $basePrice, 'attributes' => ['color' => 'Black', 'finish' => 'Standard']],
            ],
            default => [
                ['label' => 'Standard', 'price' => $basePrice, 'attributes' => ['finish' => 'Standard']],
            ],
        };
    }

    private function initialStockForIndex(int $productIndex, int $variantIndex): int
    {
        // Guarantee several out-of-stock & low-stock variants for admin alerts & UI badges.
        if ($productIndex % 35 === 29 && $variantIndex === 0) {
            return 0;
        }
        if ($productIndex % 20 === 14 && $variantIndex === 0) {
            return 3;
        }

        return 18 + (($productIndex * 3 + $variantIndex * 5) % 45);
    }
}
