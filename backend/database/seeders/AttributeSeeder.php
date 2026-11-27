<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Catalog attributes may only be seeded in local or testing environments.');

        $definitions = [
            [
                'name' => 'Color',
                'slug' => 'color',
                'type' => 'color',
                'values' => [
                    ['Black', '#111827'],
                    ['White', '#F8FAFC'],
                    ['Indigo Blue', '#1D4ED8'],
                    ['Crimson Red', '#B91C1C'],
                    ['Forest Green', '#15803D'],
                    ['Golden Saffron', '#D97706'],
                    ['Natural Bamboo', '#C4A484'],
                    ['Silver', '#94A3B8'],
                ],
            ],
            [
                'name' => 'Size',
                'slug' => 'size',
                'type' => 'size',
                'values' => [
                    ['XS', null],
                    ['S', null],
                    ['M', null],
                    ['L', null],
                    ['XL', null],
                    ['Free Size', null],
                    ['EU 39', null],
                    ['EU 40', null],
                    ['EU 41', null],
                    ['EU 42', null],
                    ['EU 43', null],
                ],
            ],
            [
                'name' => 'Weight / Pack',
                'slug' => 'weight-pack',
                'type' => 'select',
                'values' => [
                    ['100g', null],
                    ['250g', null],
                    ['500g', null],
                    ['1kg', null],
                    ['2kg', null],
                    ['5kg', null],
                ],
            ],
            [
                'name' => 'Material',
                'slug' => 'material',
                'type' => 'select',
                'values' => [
                    ['Handloom Silk', null],
                    ['Organic Cotton', null],
                    ['Woven Bamboo', null],
                    ['Natural Rattan', null],
                    ['Ceramic', null],
                    ['Stainless Steel', null],
                ],
            ],
            [
                'name' => 'Storage',
                'slug' => 'storage',
                'type' => 'select',
                'values' => [
                    ['64GB', null],
                    ['128GB', null],
                    ['256GB', null],
                    ['512GB', null],
                ],
            ],
            [
                'name' => 'Capacity',
                'slug' => 'capacity',
                'type' => 'select',
                'values' => [
                    ['250ml', null],
                    ['500ml', null],
                    ['1L', null],
                    ['20L', null],
                ],
            ],
            [
                'name' => 'Finish',
                'slug' => 'finish',
                'type' => 'select',
                'values' => [
                    ['Matte', null],
                    ['Glossy', null],
                    ['Whole Bean', null],
                    ['Ground', null],
                    ['Whole', null],
                    ['Standard', null],
                ],
            ],
        ];

        foreach ($definitions as $def) {
            $attribute = Attribute::firstOrCreate(
                ['slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'type' => $def['type'],
                    'is_filterable' => true,
                ]
            );

            foreach ($def['values'] as [$value, $swatch]) {
                AttributeValue::firstOrCreate(
                    ['attribute_id' => $attribute->id, 'value' => $value],
                    ['swatch_color' => $swatch]
                );
            }
        }
    }
}
