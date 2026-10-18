<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        // One value per attribute keeps both tables at twenty records.
        $attributes = [
            ['Color', 'Black', 'color'], ['Size', 'M', 'size'],
            ['Material', 'Cotton', 'select'], ['Storage', '256 GB', 'select'],
            ['Connectivity', 'Bluetooth', 'select'], ['Capacity', '1 litre', 'select'],
            ['Finish', 'Matte', 'select'], ['Pattern', 'Solid', 'select'],
            ['Fit', 'Regular', 'select'], ['Length', 'Standard', 'select'],
            ['Width', 'Medium', 'select'], ['Weight', 'Lightweight', 'select'],
            ['Scent', 'Unscented', 'select'], ['Volume', '30 ml', 'select'],
            ['Power', 'USB-C', 'select'], ['Resolution', '4K', 'select'],
            ['Edition', 'Standard', 'select'], ['Language', 'English', 'select'],
            ['Age Group', 'Adult', 'select'], ['Pack Size', 'Single', 'select'],
        ];

        foreach ($attributes as [$name, $value, $type]) {
            $attribute = Attribute::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'type' => $type,
                'is_filterable' => true,
            ]);
            $attribute->values()->create([
                'value' => $value,
                'swatch_color' => $type === 'color' ? '#111827' : null,
            ]);
        }
    }
}
