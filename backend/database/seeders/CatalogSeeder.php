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
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electronics', 'Fashion', 'Shoes', 'Beauty', 'Accessories',
            'Home', 'Sports', 'Books', 'Toys', 'Groceries',
            'Office', 'Bedding', 'Fitness', 'Outdoor', 'Wellness',
            'Lighting', 'Stationery', 'Kitchen', 'Travel', 'Storage',
        ];
        $brands = [
            'TechNova', 'CloudStep', 'Urban Threads', 'Elara Beauty', 'Voltex',
            'Northpeek', 'Hearth & Home', 'Peak Motion', 'Paper & Co', 'Daily Harvest',
            'DeskWorks', 'SoftNest', 'FlexFit', 'TrailGuard', 'Calm Studio',
            'Bright Living', 'Ink & Page', 'Kitchen Craft', 'Wander Pack', 'DataVault',
        ];

        foreach ($categories as $index => $name) {
            Category::create([
                'name' => $name, 'slug' => Str::slug($name),
                'description' => $name.' essentials for everyday life.',
                'is_active' => true, 'sort_order' => $index + 1,
            ]);
        }
        foreach ($brands as $name) {
            Brand::create([
                'name' => $name, 'slug' => Str::slug($name),
                'description' => 'Discover everyday essentials from '.$name.'.',
                'is_active' => true,
            ]);
        }

        $this->call(AttributeSeeder::class);
        $shops = Shop::active()->orderBy('id')->get();
        $admin = User::where('email', 'admin@ekhmer.com')->firstOrFail();
        $values = AttributeValue::with('attribute')->get()->keyBy('attribute.slug');
        $categoryIds = Category::pluck('id', 'name');
        $brandIds = Brand::pluck('id', 'name');

        foreach ($this->products() as $index => [$name, $category, $brand, $price, $attribute]) {
            $shop = $shops[$index % $shops->count()];
            $slug = Str::slug($name);
            $sku = 'DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $value = $values[$attribute];
            $product = Product::create([
                'category_id' => $categoryIds[$category],
                'brand_id' => $brandIds[$brand],
                'shop_id' => $shop->id,
                'name' => $name, 'slug' => $slug,
                'description' => $name.' from '.$brand.'. A practical choice for everyday use.',
                'short_description' => $name.' by '.$brand.'.',
                'price' => $price, 'compare_at_price' => round($price * 1.2, 2),
                'cost_price' => round($price * 0.6, 2),
                'sku' => $sku, 'barcode' => 'DEMO-P-'.($index + 1),
                'is_featured' => $index < 8, 'is_active' => true,
                'rating_avg' => 0, 'rating_count' => 0,
                'meta_title' => $name, 'meta_description' => $name.' by '.$brand.'.',
                'seo_keywords' => implode(', ', [$name, $brand, $category]),
            ]);
            $image = ProductImage::create([
                'product_id' => $product->id,
                'image_path' => 'images/products/'.$slug.'/1.jpg',
                'alt_text' => $name, 'sort_order' => 1, 'is_cover' => true,
            ]);
            $variant = ProductVariant::create([
                'product_id' => $product->id, 'image_id' => $image->id, 'shop_id' => $shop->id,
                'name' => $value->value, 'sku' => $sku.'-01',
                'barcode' => 'DEMO-V-'.($index + 1),
                'price' => $price, 'cost_price' => round($price * 0.6, 2),
                'compare_at_price' => $product->compare_at_price,
                'is_default' => true, 'is_active' => true,
            ]);
            VariantAttributeValue::create([
                'product_variant_id' => $variant->id, 'attribute_value_id' => $value->id,
            ]);
            $quantity = $index < 3 ? $index + 1 : 20 + $index;
            $inventory = Inventory::create([
                'product_variant_id' => $variant->id, 'shop_id' => $shop->id,
                'quantity' => $quantity, 'reserved_quantity' => 0,
                'low_stock_threshold' => 5, 'sold_count' => 2,
            ]);
            InventoryTransaction::create([
                'inventory_id' => $inventory->id, 'shop_id' => $shop->id,
                'created_by' => $admin->id, 'type' => 'in',
                'quantity' => $quantity, 'balance_after' => $quantity,
                'reference' => 'SEED-'.$variant->sku,
                'note' => 'Opening balance after the historical demo order; returned goods were not restocked.',
            ]);
        }
    }

    /** @return list<array{string, string, string, float|int, string}> */
    private function products(): array
    {
        return [
            ['Aurora Wireless Headphones', 'Electronics', 'TechNova', 249.99, 'connectivity'],
            ['Pulse Smartwatch Pro', 'Electronics', 'TechNova', 329, 'color'],
            ['Vertex 4K Action Camera', 'Electronics', 'Voltex', 399.99, 'resolution'],
            ['AeroLite Running Shoes', 'Shoes', 'CloudStep', 139.99, 'size'],
            ['Meridian Slim Fit Blazer', 'Fashion', 'Urban Threads', 189.99, 'fit'],
            ['Nova Linen Shirt', 'Fashion', 'Urban Threads', 59.99, 'material'],
            ['Lumiere Hydra Glow Serum', 'Beauty', 'Elara Beauty', 42, 'volume'],
            ['Velvet Matte Lipstick Ruby', 'Beauty', 'Elara Beauty', 24, 'finish'],
            ['Orbit Everyday Backpack', 'Travel', 'Wander Pack', 119.99, 'capacity'],
            ['Cascade Leather Tote', 'Accessories', 'Northpeek', 219, 'pattern'],
            ['Serene Wooden Desk Lamp', 'Lighting', 'Bright Living', 79.99, 'power'],
            ['English Practice Workbook', 'Stationery', 'Ink & Page', 12.99, 'language'],
            ['SoftNest Cotton Blanket', 'Bedding', 'SoftNest', 49.99, 'length'],
            ['FlexFit Yoga Mat', 'Fitness', 'FlexFit', 29.99, 'width'],
            ['Peak Motion Dumbbell', 'Sports', 'Peak Motion', 39.99, 'weight'],
            ['Calm Studio Diffuser', 'Wellness', 'Calm Studio', 34.99, 'scent'],
            ['Paper and Co Adventure Novel', 'Books', 'Paper & Co', 19.99, 'edition'],
            ['TrailGuard Cycling Helmet', 'Outdoor', 'TrailGuard', 64.99, 'age-group'],
            ['Daily Harvest Rice', 'Groceries', 'Daily Harvest', 9.99, 'pack-size'],
            ['DataVault USB Drive', 'Storage', 'DataVault', 24.99, 'storage'],
        ];
    }
}
