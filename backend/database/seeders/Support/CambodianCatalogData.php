<?php

namespace Database\Seeders\Support;

/**
 * Curated 15-category, 250-product fictional Cambodian marketplace dataset.
 *
 * All shops, brands, and domestic artisanal items are fictional development
 * fixtures; imported electronics explicitly state international distribution.
 */
final class CambodianCatalogData
{
    /** @return list<array{name: string, slug: string, icon: string, description: string}> */
    public static function categories(): array
    {
        return [
            ['name' => 'Khmer Food & Snacks', 'slug' => 'khmer-food-snacks', 'icon' => 'utensils', 'description' => 'Traditional Cambodian snacks, palm sugar, dried fruit, and pantry condiments (អាហារ និងបង្អែមខ្មែរ).'],
            ['name' => 'Rice & Agricultural Products', 'slug' => 'rice-agricultural-products', 'icon' => 'tractor', 'description' => 'Cambodian fragrant jasmine rice, whole grains, beans, and farm-direct dry produce (អង្ករ និងកសិផលខ្មែរ).'],
            ['name' => 'Kampot Pepper & Spices', 'slug' => 'kampot-pepper-spices', 'icon' => 'sparkles', 'description' => 'Whole peppercorns, sea salt, lemongrass, turmeric, and Khmer spice blends from Kampot (ម្រេចកំពត និងគ្រឿងទេស).'],
            ['name' => 'Cambodian Coffee & Beverages', 'slug' => 'cambodian-coffee-beverages', 'icon' => 'shoppingbasket', 'description' => 'Mondulkiri roasted coffee beans, lotus leaf tea, ginger infusions, and local drinks (កាហ្វេ និងភេសជ្ជៈខ្មែរ).'],
            ['name' => 'Traditional Khmer Clothing', 'slug' => 'traditional-khmer-clothing', 'icon' => 'shirt', 'description' => 'Sampot, Av Pak blouses, ceremonial tunics, and everyday Cambodian heritage wear (សម្លៀកបំពាក់ប្រពៃណីខ្មែរ).'],
            ['name' => 'Krama & Cambodian Silk', 'slug' => 'krama-cambodian-silk', 'icon' => 'gem', 'description' => 'Handwoven cotton krama scarves, golden silk wraps, and Siem Reap loom textiles (ក្រមា និងសូត្រខ្មែរ).'],
            ['name' => 'Handicrafts & Souvenirs', 'slug' => 'handicrafts-souvenirs', 'icon' => 'lampdesk', 'description' => 'Carved sandstone keepsakes, lacquerware trays, brass bells, and artisan gifts (សិប្បកម្ម និងវត្ថុអនុស្សាវរីយ៍).'],
            ['name' => 'Bamboo & Rattan Products', 'slug' => 'bamboo-rattan-products', 'icon' => 'package', 'description' => 'Woven bamboo baskets, rattan serving trays, mats, and natural fiber storage (ផលិតផលឫស្សី និងផ្តៅ).'],
            ['name' => 'Fashion & Shoes', 'slug' => 'fashion-shoes', 'icon' => 'footprints', 'description' => 'Everyday clothing, tropical linen wear, sneakers, and footwear for Cambodia (សម្លៀកបំពាក់ និងស្បែកជើង).'],
            ['name' => 'Electronics & Accessories', 'slug' => 'electronics-accessories', 'icon' => 'smartphone', 'description' => 'Imported smartphones, audio gear, chargers, and desk accessories sold in Cambodia (គ្រឿងអេឡិចត្រូនិក).'],
            ['name' => 'Beauty & Personal Care', 'slug' => 'beauty-personal-care', 'icon' => 'flower2', 'description' => 'Botanical soaps, tropical skincare, coconut hair oils, and daily hygiene essentials (សម្រស់ និងថែរក្សាខ្លួន).'],
            ['name' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'icon' => 'cookingpot', 'description' => 'Cookware, rice cookers, ceramic tableware, cotton bedding, and organizers (សម្ភារៈផ្ទះ និងផ្ទះបាយ).'],
            ['name' => 'Books & Stationery', 'slug' => 'books-stationery', 'icon' => 'bookopen', 'description' => 'Khmer and English books, bilingual guides, notebooks, and office supplies (សៀវភៅ និងសម្ភារៈសិក្សា).'],
            ['name' => 'Sports & Outdoor', 'slug' => 'sports-outdoor', 'icon' => 'dumbbell', 'description' => 'Cycling gear, yoga mats, hydration bottles, and tropical travel backpacks (កីឡា និងដំណើរកម្សាន្ត).'],
            ['name' => 'Baby & Family Products', 'slug' => 'baby-family-products', 'icon' => 'baby', 'description' => 'Gentle baby care, feeding bottles, breathable cotton wraps, and family essentials (ផលិតផលទារក និងគ្រួសារ).'],
        ];
    }

    /** @return list<array{name: string, slug: string, description: string}> */
    public static function brands(): array
    {
        return [
            ['name' => 'Champa Kitchen', 'slug' => 'champa-kitchen', 'description' => 'Curated Khmer pantry snacks, palm sugars, and homestyle sauces.'],
            ['name' => 'Khmer Harvest', 'slug' => 'khmer-harvest', 'description' => 'Cambodian agricultural grains, dry fruits, and farm staples.'],
            ['name' => 'Kampot Terroir', 'slug' => 'kampot-terroir', 'description' => 'Fictional demo spice collective sourcing Kampot peppercorns and sea salt.'],
            ['name' => 'Mondulkiri Roast', 'slug' => 'mondulkiri-roast', 'description' => 'Highland Cambodian roasted coffee and herbal tea infusions.'],
            ['name' => 'Angkor Loom', 'slug' => 'angkor-loom', 'description' => 'Traditional handwoven krama, golden silk scarves, and Khmer attire.'],
            ['name' => 'Bakong Artisans', 'slug' => 'bakong-artisans', 'description' => 'Siem Reap woodcraft, lacquerware, and cultural souvenirs.'],
            ['name' => 'Mekong Craft', 'slug' => 'mekong-craft', 'description' => 'Woven bamboo and rattan home accessories from rural workshops.'],
            ['name' => 'Sovanna Atelier', 'slug' => 'sovanna-atelier', 'description' => 'Tropical-weight everyday apparel tailored for Phnom Penh living.'],
            ['name' => 'Tonle Botanicals', 'slug' => 'tonle-botanicals', 'description' => 'Lemongrass, moringa, and coconut-oil personal care essentials.'],
            ['name' => 'Phsar Home', 'slug' => 'phsar-home', 'description' => 'Practical Cambodian kitchenware, ceramics, and cotton bedding.'],
            ['name' => 'Pannha Press', 'slug' => 'pannha-press', 'description' => 'Khmer language workbooks, cultural reads, and school stationery.'],
            ['name' => 'Battambang Footwear', 'slug' => 'battambang-footwear', 'description' => 'Durable everyday sandals, canvas sneakers, and rain footwear.'],
            ['name' => 'Apple (Imported)', 'slug' => 'apple-imported', 'description' => 'Imported Apple electronics stocked by authorized Cambodian retailers.'],
            ['name' => 'Samsung (Imported)', 'slug' => 'samsung-imported', 'description' => 'Imported Samsung mobile devices and home displays sold in Cambodia.'],
            ['name' => 'Sony (Imported)', 'slug' => 'sony-imported', 'description' => 'Imported Sony audio headphones, speakers, and imaging gear.'],
            ['name' => 'Anker (Imported)', 'slug' => 'anker-imported', 'description' => 'Imported Anker power banks, GaN chargers, and USB-C cables.'],
            ['name' => 'Logitech (Imported)', 'slug' => 'logitech-imported', 'description' => 'Imported Logitech keyboards, mice, and webcams for study and work.'],
            ['name' => 'Xiaomi (Imported)', 'slug' => 'xiaomi-imported', 'description' => 'Imported Xiaomi smart home accessories and wearable bands.'],
            ['name' => 'Nike (Imported)', 'slug' => 'nike-imported', 'description' => 'Imported athletic footwear and training apparel sold in Cambodia.'],
            ['name' => 'Decathlon (Imported)', 'slug' => 'decathlon-imported', 'description' => 'Imported sports equipment, camping gear, and fitness accessories.'],
            ['name' => 'Lock&Lock (Imported)', 'slug' => 'lock-lock-imported', 'description' => 'Imported food containers, vacuum flasks, and small kitchenware.'],
            ['name' => 'Pigeon (Imported)', 'slug' => 'pigeon-imported', 'description' => 'Imported baby feeding bottles, wipes, and gentle infant care.'],
        ];
    }

    /**
     * Returns exactly 250 distinct Cambodian marketplace products across all 15 categories.
     *
     * @return list<array{
     *   name: string,
     *   khmer: string,
     *   category: string,
     *   brand: string,
     *   shop: string,
     *   price: float,
     *   variant_mode: string,
     *   summary: string,
     *   origin_note: string
     * }>
     */
    public static function products(): array
    {
        $products = [];

        $groups = [
            // 1. Khmer Food & Snacks (17)
            [
                'category' => 'khmer-food-snacks',
                'brand' => 'champa-kitchen',
                'shop' => 'CHAMPA',
                'variant_mode' => 'weight',
                'origin_note' => 'Prepared and packed in Cambodia using locally sourced ingredients.',
                'items' => [
                    ['Kampong Speu Palm Sugar Blocks', 'ស្ករត្នោតកំពង់ស្ពឺ', 4.50, 'Unrefined golden palm sugar molded into traditional cooking cakes.'],
                    ['Natural Palm Sugar Syrup', 'ទឹកស្ករត្នោតធម្មជាតិ', 5.20, 'Pourable amber palm nectar ideal for iced coffee and Khmer desserts.'],
                    ['Roasted Kampong Thom Cashew Nuts', 'គ្រាប់ស្វាយចន្ទីលីង', 9.80, 'Crisp lightly salted whole cashews roasted in small batches.'],
                    ['Chili Lime Cashew Crunch', 'ស្វាយចន្ទីរសជាតិម្ទេសក្រូចឆ្មារ', 10.40, 'Savory cashew snack tossed with Kampot sea salt and chili zest.'],
                    ['Sun-Dried Keo Romeat Mango Slices', 'ដំណាប់ស្វាយកែវរមៀត', 6.50, 'Chewy unsweetened dried mango strips from orchard harvests.'],
                    ['Dried Banana Sesame Crisps', 'ចេកឆាបល្ង', 3.80, 'Thin caramelized saba banana chips toasted with black sesame.'],
                    ['Siem Reap Sesame Rice Crackers', 'នំអន្សមបន្ទះល្ង', 4.10, 'Crunchy puffed rice wafers brushed with palm syrup and sesame.'],
                    ['Toasted Coconut Crispy Rolls', 'នំខ្មែរដូងអាំង', 4.90, 'Light wafer rolls baked with fresh coconut cream and eggs.'],
                    ['Tamarind Chili Candy Jar', 'តំណាប់អម្ពិលម្ទេស', 3.60, 'Sweet-tangy tamarind bites balanced with mild chili and salt.'],
                    ['Honey Roasted Peanut Brittle', 'នំល្ងសណ្តែកដី', 4.40, 'Classic market snack of roasted peanuts bound in golden cane caramel.'],
                    ['Battambang Crispy Pork Floss', 'សាច់ជ្រូកផាត់បាត់ដំបង', 8.90, 'Savory shredded pork floss suited for congee and steamed rice.'],
                    ['Garlic Fried Shallot Topping', 'ខ្ទឹមក្រហមបំពង', 3.90, 'Crisp golden shallots fried cleanly for Khmer noodle bowls.'],
                    ['Kampong Chhnang Fermented Fish Paste', 'ប្រហុកសាច់កំពង់ឆ្នាំង', 7.50, 'Sealed glass jar of traditional boneless prahok for dips and soups.'],
                    ['Smoked Fish Tamarind Dip Base', 'ទឹកត្រីកោះកុងគ្រឿងស្រេច', 5.60, 'Savory-sour dipping condiment crafted for grilled seafood.'],
                    ['Khmer Red Curry Spice Paste', 'គ្រឿងការីក្រហមខ្មែរ', 4.20, 'Fresh lemongrass, galangal, and kaffir lime paste ready for cooking.'],
                    ['Yellow Kroeung Aromatic Paste', 'គ្រឿងបុកខ្មែរ', 4.00, 'Traditional mortar-ground herb base for fish amok and stir-fries.'],
                    ['Coconut Milk Steamed Rice Cake Mix', 'ម្សៅនំកង និងនំខ្មែរ', 4.80, 'Ready-to-steam glutinous rice flour blend for festive desserts.'],
                ],
            ],

            // 2. Rice & Agricultural Products (17)
            [
                'category' => 'rice-agricultural-products',
                'brand' => 'khmer-harvest',
                'shop' => 'MEKONG',
                'variant_mode' => 'rice_weight',
                'origin_note' => 'Harvested from Cambodian smallholder farms and milled for household freshness.',
                'items' => [
                    ['Phka Rumduol Fragrant Jasmine Rice', 'អង្ករផ្ការំដួលក្រអូប', 7.80, 'Aromatic long-grain white rice prized for its soft, fluffy steamed texture.'],
                    ['Sen Kra Ob Early Jasmine Rice', 'អង្ករសែនក្រអូប', 7.20, 'Daily fragrant white rice with balanced sweetness and clean grain length.'],
                    ['Somali Golden Fragrant Rice', 'អង្ករនាងម៉ាលី', 6.90, 'Everyday Cambodian jasmine grain great for fried rice and family meals.'],
                    ['Battambang Broken Rice (Angkor Chrok)', 'អង្ករចុងបាត់ដំបង', 5.40, 'Fine broken jasmine grains ideal for Bai Sach Chrouk and congee.'],
                    ['Khmer Sticky Glutinous Rice', 'អង្ករដំណើបខ្មែរ', 6.40, 'Classic white sticky rice suited for mango desserts and bamboo rice cakes.'],
                    ['Red Cargo Whole Grain Rice', 'អង្ករសម្រូបក្រហម', 8.50, 'Nutty unpolished red rice retaining natural bran fiber and minerals.'],
                    ['Purple Black Rice Berry', 'អង្ករដំណើបខ្មៅ', 9.20, 'Deep purple antioxidant-rich grain for porridge and steamed bowls.'],
                    ['Brown Hom Mali Parboiled Rice', 'អង្ករសម្រូបសុខភាព', 8.10, 'Slow-release whole grain brown rice for everyday healthy dining.'],
                    ['Prey Veng Dried Mung Beans', 'សណ្តែកបាយព្រៃវែង', 4.30, 'Clean hulled green mung beans for sweet soups and bean sprouts.'],
                    ['Split Yellow Mung Beans', 'សណ្តែកបាយបកសំបក', 4.60, 'Steaming-ready split beans used in Khmer cakes and sticky rice toppings.'],
                    ['Takeo Whole Black Sesame Seeds', 'ល្ងខ្មៅតាកែវ', 5.80, 'Sun-dried aromatic black sesame ready for roasting and desserts.'],
                    ['Kandal Roasted White Sesame', 'ល្ងសលីងកណ្តាល', 5.50, 'Clean white sesame seeds for salads, buns, and palm sugar snacks.'],
                    ['Red Peanuts Raw Shelled', 'សណ្តែកដីក្រហម', 4.90, 'Freshly shelled red-skin peanuts for boiling, roasting, or satay sauce.'],
                    ['Dried Lotus Seeds Whole', 'គ្រាប់ឈូកក្រៀម', 8.40, 'Peeled dried lotus kernels for sweet herbal soups and rice porridge.'],
                    ['Kampong Thom Dried Job Tears', 'គ្រាប់ស្គួយស្ងួត', 5.90, 'Nutritious barley-like grain popular in Khmer dessert broths.'],
                    ['Red Kidney Beans Agricultural Pack', 'សណ្តែកក្រហម', 4.70, 'Hearty red beans for desserts, curries, and stews.'],
                    ['Sun-Cured Yellow Corn Kernels', 'គ្រាប់ពោតស្ងួត', 3.80, 'Clean whole yellow corn for sweet corn porridge and savory stir-fries.'],
                ],
            ],

            // 3. Kampot Pepper & Spices (17)
            [
                'category' => 'kampot-pepper-spices',
                'brand' => 'kampot-terroir',
                'shop' => 'KAMPOT',
                'variant_mode' => 'spice_weight',
                'origin_note' => 'Fictional Kampot spice demo listing focused on coastal Cambodian terroir without legal certification claims.',
                'items' => [
                    ['Kampot Black Peppercorns Whole', 'ម្រេចខ្មៅកំពតគ្រាប់', 8.50, 'Sun-dried black peppercorns with warm eucalyptus and citrus heat.'],
                    ['Kampot Black Pepper Cracked Coarse', 'ម្រេចខ្មៅកំពតកិនម៉ដ្ឋ', 8.80, 'Coarsely crushed black pepper ideal for steak and Lok Lak marinades.'],
                    ['Kampot Red Peppercorns Late Harvest', 'ម្រេចក្រហមកំពត', 12.90, 'Ripe red berries picked at peak maturity for fruity aroma and mellow warmth.'],
                    ['Kampot White Peppercorns Cleaned', 'ម្រេចសកំពត', 13.50, 'Husked ripe peppercorns delivering sharp floral heat for light broths.'],
                    ['Salted Green Kampot Pepper in Brine', 'ម្រេចខ្ចីកំពតត្រាំទឹកអំបិល', 11.20, 'Plump young green pepper clusters preserved in coastal sea brine.'],
                    ['Dehydrated Crunchy Green Peppercorns', 'ម្រេចខ្ចីកំពតសម្ងួត', 12.40, 'Air-dried green peppercorns ready to snack or finish grilled seafood.'],
                    ['Kampot Smoked Pepper Mill Blend', 'ម្រេចកំពតឆ្អើរ', 10.60, 'Lightly smoked black and red peppercorns with savory depth.'],
                    ['Kampot Fleur de Sel Sea Salt', 'អំបិលផ្កាដីកំពត', 4.80, 'Hand-skimmed flaky sea salt crystals from coastal salt pans.'],
                    ['Lemongrass & Kampot Salt Rub', 'អំបិលម្រេចស្លឹកគ្រៃ', 5.40, 'Zesty finishing salt blended with dried lemongrass and pepper.'],
                    ['Chili Lime Kampot Dipping Salt', 'អំបិលម្ទេសក្រូចឆ្មារកំពត', 4.50, 'Classic Cambodian fruit-dipping salt with crushed bird-eye chili.'],
                    ['Dried Wild Turmeric Slices', 'រមៀតក្រៀមកំពត', 5.20, 'Fragrant golden turmeric slices for teas and curry pastes.'],
                    ['Sun-Dried Lemongrass Rings', 'ស្លឹកគ្រៃហាន់ស្ងួត', 4.60, 'Cleanly sliced aromatic stalks for broth infusions and marinades.'],
                    ['Kaffir Lime Leaf Flakes', 'ស្លឹកក្រូចសើចស្ងួត', 5.10, 'Intensely citrusy dried leaves sealed for soup stocks and amok.'],
                    ['Dried Galangal Root Coins', 'រំដេងហាន់ស្ងួត', 4.90, 'Crisp dried galangal coins essential for sour Cambodian soups.'],
                    ['Khmer Five-Spice Lok Lak Seasoning', 'ម្សៅប្រហើរឡុកឡាក់', 5.80, 'Balanced blend of Kampot black pepper, garlic, star anise, and clove.'],
                    ['Cardamom & Clove Kampot Trio Gift Box', 'ប្រអប់អំណោយគ្រឿងទេសកំពត', 19.50, 'Curated presentation set pairing black, red, and white peppercorns.'],
                    ['Refillable Brass Pepper Grinder Set', 'ម៉ាស៊ីនកិនម្រេចស្ពាន់', 16.00, 'Sturdy tabletop spice mill paired with a starter pouch of black peppercorns.'],
                ],
            ],

            // 4. Cambodian Coffee & Beverages (17)
            [
                'category' => 'cambodian-coffee-beverages',
                'brand' => 'mondulkiri-roast',
                'shop' => 'ROASTERY',
                'variant_mode' => 'coffee',
                'origin_note' => 'Small-batch roasted and packaged by local Cambodian demo beverage makers.',
                'items' => [
                    ['Mondulkiri Highland Dark Roast Coffee', 'កាហ្វេមណ្ឌលគិរីលីងចាស់', 11.50, 'Bold cocoa-toned highland roast crafted for Cambodian iced milk coffee.'],
                    ['Mondulkiri Medium Arabica Blend', 'កាហ្វេអារ៉ាប៊ីកាមណ្ឌលគិរី', 13.00, 'Smooth nutty medium roast suited for pour-over and drip brewers.'],
                    ['Ratanakiri Volcanic Estate Robusta', 'កាហ្វេរតនគិរី', 9.90, 'Full-bodied local robusta with thick crema and caramelized sweetness.'],
                    ['Cardamom Spiced Khmer Drip Coffee', 'កាហ្វេបន្ថែមក្រវាញខ្មែរ', 12.20, 'Aromatic ground blend infused with warm wild cardamom notes.'],
                    ['Phnom Penh Iced Coffee Concentrate Pack', 'កាហ្វេទឹកកករូបមន្តភ្នំពេញ', 8.90, 'Convenient single-steep sachets for quick iced coffee at home.'],
                    ['Preah Vihear Wild Honey Cold Brew Pack', 'កាហ្វេត្រជាក់ទឹកឃ្មុំព្រៃ', 10.50, 'Coarse-ground cold brew pouches paired with natural sweetness.'],
                    ['Khmer Lotus Leaf Herbal Tea', 'តែស្លឹកឈូកខ្មែរ', 6.20, 'Caffeine-free dried lotus leaf infusion with a crisp floral finish.'],
                    ['Dried Butterfly Pea Flower Tea', 'តែផ្កាអញ្ជ័ន', 5.80, 'Vibrant blue herbal petals that turn violet with a splash of lime.'],
                    ['Lemongrass Pandan Iced Tea Blend', 'តែស្លឹកគ្រៃស្លឹកតើយ', 5.50, 'Refreshing tropical herbal loose-leaf blend served hot or iced.'],
                    ['Roasted Ginger & Palm Sugar Latte Mix', 'តែខ្ញីស្ករត្នោត', 6.80, 'Warming soluble ginger drink sweetened lightly with palm nectar.'],
                    ['Bael Fruit Sun-Dried Tea Slices', 'តែផ្លែព្នៅស្ងួត', 5.40, 'Traditional roasted bael slices delivering a malty caramel aroma.'],
                    ['Moringa Leaf Wellness Tea Tin', 'តែស្លឹកម្រុំ', 7.20, 'Clean shade-dried moringa leaves packed in an airtight tin.'],
                    ['Turmeric Citrus Morning Infusion', 'តែរមៀតក្រូចឆ្មារ', 6.40, 'Bright herbal tea pairing sun-dried turmeric with kaffir lime peel.'],
                    ['Chrysanthemum & Jasmine Bud Tea', 'តែផ្កាម្លិះ', 6.90, 'Soothing floral tea blossoms ideal after rich Khmer meals.'],
                    ['Passionfruit & Honey Drink Syrup', 'ទឹកស៊ីរ៉ូផ្លែផាសិន', 6.50, 'Concentrated fruit cordial ready to mix with sparkling or still water.'],
                    ['Tamarind Refresher Bottle Pack', 'ភេសជ្ជៈអម្ពិលត្រជាក់', 7.40, 'Six-pack of lightly sweetened roasted tamarind refresher drinks.'],
                    ['Phnom Penh Filter Phin Brewer Cup', 'ប្រដាប់ឆុងកាហ្វេខ្មែរ', 7.90, 'Stainless steel single-cup dripper crafted for dense Khmer iced coffee.'],
                ],
            ],

            // 5. Traditional Khmer Clothing (17)
            [
                'category' => 'traditional-khmer-clothing',
                'brand' => 'angkor-loom',
                'shop' => 'ANGKOR',
                'variant_mode' => 'clothing',
                'origin_note' => 'Traditional cuts inspired by Cambodian heritage tailoring.',
                'items' => [
                    ['Ceremonial Sampot Hol Skirt', 'សំពត់ហូលប្រពៃណី', 68.00, 'Woven patterned traditional wrap skirt for weddings and Pchum Ben.'],
                    ['Brocade Sampot Phamoung Formal Wrap', 'សំពត់ផាមួង', 74.00, 'Crisp pleated formal sampot finished in rich jewel tones.'],
                    ['Embroidered Av Pak Lace Blouse', 'អាវប៉ាក់ខ្មែរ', 42.00, 'Tailored floral-lace collared blouse worn cleanly with sampot wraps.'],
                    ['Standing-Collar Av Neary Tunic', 'អាវនារីកបិទ', 34.00, 'Modern Khmer standing-collar top with delicate pearl buttons.'],
                    ['Men Kor Krong Formal Mandarin Jacket', 'អាវកត្រង់បុរស', 46.00, 'Structured traditional Khmer men’s jacket for ceremonies and family gatherings.'],
                    ['Cotton Blend Sampot Chang Kben Wrap', 'សំពត់ចងក្បិន', 39.00, 'Pre-pleated traditional lower garment designed for easy dressing.'],
                    ['Silk Sbai Ceremonial Shoulder Sash', 'ស្បៃពាក់ប្រពៃណី', 29.00, 'Embroidered diagonal shoulder sash adding shimmer to festive attire.'],
                    ['Woven Khmer Water Festival Tunic', 'អាវបុណ្យអុំទូក', 26.00, 'Breathable cotton festive shirt trimmed with Angkor-inspired borders.'],
                    ['Classic Temple Cotton Kurta Shirt', 'អាវកប្បាសខ្មែរ', 22.50, 'Relaxed breathable tunic suitable for pagoda visits and humid days.'],
                    ['High-Waist Modern Midi Sampot', 'សំពត់សម័យទំនើប', 31.00, 'Tailored everyday wrap skirt bringing Khmer motifs to office attire.'],
                    ['Floral Silk Boat-Neck Khmer Top', 'អាវសូត្រកទូក', 36.00, 'Soft draped blouse tailored in subtle jacquard weave.'],
                    ['Youth Traditional Krama Trim Shirt', 'អាវក្មេងរំលេចក្រមា', 16.50, 'Comfortable school-celebration cotton shirt with krama pocket trim.'],
                    ['Youth Ceremonial Gold-Trim Sampot Set', 'ឈុតប្រពៃណីក្មេង', 28.00, 'Two-piece festive traditional set made soft for younger wearers.'],
                    ['Wedding Groom Brocade Tunic', 'អាវកូនកំលោះ', 82.00, 'Richly woven ceremonial jacket tailored for wedding receptions.'],
                    ['Khmer Silver Belt Replica Clasp', 'ខ្សែក្រវាត់ប្រពៃណី', 24.00, 'Engraved decorative waist belt completing formal sampot styling.'],
                    ['Hand-Pleated Lotus Silk Skirt', 'សំពត់សូត្រឈូក', 54.00, 'Graceful pleated long skirt dyed in soft earthy tones.'],
                    ['Pagoda Day White Cotton Ensemble', 'ឈុតសទៅវត្ត', 32.00, 'Modest crisp white cotton blouse and wrap skirt set for temple days.'],
                ],
            ],

            // 6. Krama & Cambodian Silk (17)
            [
                'category' => 'krama-cambodian-silk',
                'brand' => 'angkor-loom',
                'shop' => 'ANGKOR',
                'variant_mode' => 'color_material',
                'origin_note' => 'Woven in Cambodia using traditional shuttle looms.',
                'items' => [
                    ['Classic Red-White Checkered Cotton Krama', 'ក្រមាក្រហមសប្រពៃណី', 6.50, 'Iconic breathable handloom cotton krama for sun shade, travel, or home use.'],
                    ['Indigo Blue Artisan Cotton Krama', 'ក្រមាពណ៌ខៀវប្រពៃណី', 7.50, 'Deep indigo-dyed cotton krama with hand-knotted fringe ends.'],
                    ['Takeo Loom Golden Silk Krama', 'ក្រមាសូត្រមាសតាកែវ', 28.00, 'Lustrous fine Cambodian golden silk krama woven on wooden treadle looms.'],
                    ['Ikat Patterned Hol Silk Scarf', 'កន្សែងសូត្រហូល', 38.00, 'Intricate tie-dyed mulberry silk scarf featuring floral diamond motifs.'],
                    ['Natural Unbleached Raw Silk Shawl', 'កន្សែងសូត្រឆៅធម្មជាតិ', 34.00, 'Textured raw silk wrap offering effortless drape for evening wear.'],
                    ['Pailin Striped Fine Cotton Krama', 'ក្រមាឆ្នូតប៉ៃលិន', 8.90, 'Lightweight soft-washed striped cotton scarf crafted for everyday comfort.'],
                    ['Saffron & Burgundy Double-Weave Krama', 'ក្រមាពីរជាន់ពណ៌លឿងទុំ', 11.50, 'Extra-plush double-weave cotton scarf ideal for cooler highlands or AC offices.'],
                    ['Lotus Stem Blend Luxury Scarf', 'កន្សែងសូត្រសរសៃឈូក', 48.00, 'Delicate artisan wrap blending golden silk with botanical lotus fiber.'],
                    ['Oversized Krama Beach & Travel Wrap', 'ក្រមាខ្នាតធំសម្រាប់ដំណើរកម្សាន្ត', 12.80, 'Generous wide krama usable as a sarong, picnic cloth, or lightweight throw.'],
                    ['Hand-Embroidered Silk Table Runner', 'ក្រណាត់តុសូត្រប៉ាក់ដៃ', 26.00, 'Ceremonial woven silk centerpiece runner with geometric border trim.'],
                    ['Checkered Krama Tote Fold Pouch', 'កាបូបក្រមាបត់បាន', 9.50, 'Reusable shopping pouch sewn cleanly from sturdy cotton krama fabric.'],
                    ['Silk Cushion Cover Pair (Hol)', 'ស្រោមខ្នើយសូត្រហូល', 24.00, 'Matching pair of woven silk throw pillow covers with hidden zippers.'],
                    ['Monochrome Charcoal Urban Krama', 'ក្រមាពណ៌ប្រផេះខ្មៅ', 9.00, 'Modern minimalist black-and-grey checkered cotton scarf for daily outfits.'],
                    ['Soft Baby Cotton Krama Swaddle', 'ក្រមាកប្បាសរុំទារក', 8.50, 'Pre-washed ultra-soft cotton weave gentle on sensitive infant skin.'],
                    ['Silk Pocket Square Gift Set', 'កន្សែងដៃសូត្រខ្មែរ', 15.00, 'Two neatly hemmed silk pocket squares boxed for formal gifts.'],
                    ['Pastel Rose & Mint Cotton Krama', 'ក្រមាកប្បាសពណ៌ស្រាល', 8.20, 'Fresh spring-toned woven cotton krama light enough for tropical afternoons.'],
                    ['Heritage Gift Box: Two Silk Kramas', 'ប្រអប់អំណោយក្រមាសូត្រ', 52.00, 'Matched pair of handwoven silk scarves packed in a keepsake gift box.'],
                ],
            ],

            // 7. Handicrafts & Souvenirs (17)
            [
                'category' => 'handicrafts-souvenirs',
                'brand' => 'bakong-artisans',
                'shop' => 'HERITAGE',
                'variant_mode' => 'finish_size',
                'origin_note' => 'Handcrafted demo artisan souvenirs produced in Cambodian workshops.',
                'items' => [
                    ['Sandstone Bayon Face Desk Bust', 'រូបចម្លាក់ថ្មភក់បាយ័ន', 29.00, 'Hand-carved miniature sandstone sculpture mounted on a wood base.'],
                    ['Carved Teak Apsara Wall Relief', 'ចម្លាក់ឈើអប្សរា', 44.00, 'Detailed carved wooden wall plaque celebrating classical Khmer dance.'],
                    ['Hand-Painted Black Lacquer Bowl', 'ចានខ្មុកខ្មែរពណ៌ខ្មៅ', 24.00, 'Multi-coat natural lacquer serving bowl accented with gold leaf trim.'],
                    ['Silk-Lined Lacquer Jewelry Box', 'ប្រអប់គ្រឿងអលង្ការខ្មុក', 28.50, 'Hinged keepsake box with red Khmer silk interior lining.'],
                    ['Repousse Silver-Finish Betel Box', 'ប្រអប់ប្រាក់ខ្មែរចម្លាក់ផ្កា', 36.00, 'Ornate pumpkin-shaped embossed metal decorative container.'],
                    ['Chased Brass Phnom Penh Wind Chime', 'កណ្តឹងខ្យល់ស្ពាន់', 19.00, 'Melodic cast-brass bell chime engraved with lotus petals.'],
                    ['Krama & Palm Wood Coaster Set (6 pcs)', 'ទ្រនាប់កែវឈើត្នោត', 14.00, 'Six turned toddy-palm wood coasters packed in a bamboo holder.'],
                    ['Toddy Palm Wood Salad Serving Set', 'ស្លាបព្រា និងសមឈើត្នោត', 12.50, 'Smooth food-safe palm wood servers carved in Kampong Speu style.'],
                    ['Hand-Painted Angkor Sunrise Postcard Pack', 'ប័ណ្ណប្រៃសណីយ៍គំនូរអង្គរ', 6.50, 'Set of twelve heavy textured art cards by local illustrators.'],
                    ['Miniature Wooden Tuk-Tuk Desk Model', 'រូបតូចរឺម៉កកង់បីខ្មែរ', 18.00, 'Movable wheels carved neatly in polished hardwood for shelf display.'],
                    ['Lotus Petal Incense Stick Holder', 'ជើងធូបផ្កាឈូកសេរ៉ាមិច', 9.80, 'Glazed Kampong Chhnang clay incense dish catching ash cleanly.'],
                    ['Shadow Puppet Leather Wall Art (Sbek Thom)', 'រូបចម្លាក់ស្បែកធំ', 32.00, 'Perforated decorative parchment silhouette framed for warm lighting.'],
                    ['Embossed Brass Lotus Tea Caddy', 'កំប៉ុងតែស្ពាន់ផ្កាឈូក', 22.00, 'Double-lid metal tea storage canister keeping leaves dry.'],
                    ['Cambodia Heritage Magnet & Keychain Trio', 'មេដែក និងខ្សែសោអនុស្សាវរីយ៍', 7.50, 'Zinc-alloy enamel souvenirs featuring temple and rumduol flower motifs.'],
                    ['Carved Coconut Shell Spoon & Bowl Duo', 'ចាន និងស្លាបព្រាត្រឡោកដូង', 11.00, 'Polished upcycled coconut shell smoothie bowl with palm wood spoon.'],
                    ['Hand-Thrown Kampong Chhnang Clay Teapot', 'កំសៀវដីដុតកំពង់ឆ្នាំង', 21.00, 'Unglazed terracotta pot crafted for mellowing dark herbal teas.'],
                    ['Ceramic Rumduol Flower Diffuser Vase', 'ថូផ្ការំដួលសេរ៉ាមិច', 16.50, 'Minimalist matte ceramic bud vase shaped like Cambodia’s national flower.'],
                ],
            ],

            // 8. Bamboo & Rattan Products (17)
            [
                'category' => 'bamboo-rattan-products',
                'brand' => 'mekong-craft',
                'shop' => 'BAMBOO',
                'variant_mode' => 'material_size',
                'origin_note' => 'Woven from renewable bamboo and rattan harvested in Cambodia.',
                'items' => [
                    ['Woven Bamboo Rice Steamer Basket', 'កន្ត្រកឫស្សីចំហុយបាយ', 11.50, 'Traditional conical bamboo sticky rice steamer basket with base stand.'],
                    ['Round Rattan Serving Tray with Handles', 'ថាសផ្តៅមូលមានដៃកាន់', 19.50, 'Tightly woven serving tray ideal for coffee sets and dining tables.'],
                    ['Stackable Bamboo Kitchen Lid Box', 'ប្រអប់ឫស្សីដាក់គ្រឿងផ្ទះបាយ', 14.00, 'Breathable woven storage container for garlic, shallots, and dried chilies.'],
                    ['Braided Rattan Bell Pendant Lampshade', 'គម្របអំពូលផ្តៅត្បាញ', 34.00, 'Warm open-weave ceiling pendant shade casting soft geometric shadows.'],
                    ['Rectangular Water Hyacinth Storage Bin', 'កន្ត្រកកំប្លោកដាក់ឥវ៉ាន់', 16.80, 'Sturdy woven organizer bin fitting standard shelving cubes cleanly.'],
                    ['Natural Bamboo Winnowing Decor Plate', 'ចង្អេរឫស្សីតុបតែង', 13.50, 'Classic woven flat tray used for serving fruit or hanging wall art.'],
                    ['Woven Rattan Picnic Hamper Basket', 'កន្ត្រកផ្តៅសម្រាប់ដំណើរកម្សាន្ត', 29.00, 'Hinged twin-lid picnic carrier with wrapped carry handle.'],
                    ['Handwoven Bamboo Table Placemat Set (4 pcs)', 'កម្រាលចានឫស្សី', 12.00, 'Heat-resistant slatted bamboo placemats finished with cotton edging.'],
                    ['Tall Rattan Laundry Hamper with Cotton Liner', 'ធុងដាក់ខោអាវផ្តៅ', 38.00, 'Ventilated tall corner hamper featuring a washable cotton drawstring bag.'],
                    ['Bamboo Cutlery Drawer Organizer', 'ប្រអប់រៀបចំស្លាបព្រាឫស្សី', 15.50, 'Five-compartment solid bamboo tray keeping kitchen drawers tidy.'],
                    ['Polished Bamboo Reusable Chopsticks (10 pairs)', 'ចង្កឹះឫស្សីធម្មជាតិ', 6.50, 'Smooth splinter-free dining chopsticks tied with krama ribbon.'],
                    ['Woven Rattan Tissue Box Cover', 'ប្រអប់ក្រដាសជូតមាត់ផ្តៅ', 9.90, 'Natural woven cover concealing standard rectangular tissue cartons.'],
                    ['Foldable Reed & Bamboo Floor Mat', 'កន្ទេលឫស្សីបត់បាន', 24.00, 'Cool breathable woven sleeping or meditation mat with carry strap.'],
                    ['Three-Tier Bamboo Spice & Jar Rack', 'ធ្នើឫស្សីដាក់កែវគ្រឿងទេស', 18.50, 'Stepped countertop rack organizing Kampot pepper and pantry jars.'],
                    ['Miniature Woven Bamboo Sticky Rice Pot (Kratip)', 'កន្ត្រកបាយដំណើបតូច', 8.90, 'Insulated lidded bamboo carrier keeping steamed rice warm at table.'],
                    ['Rattan Bread & Pastry Cloche Dome', 'គម្របនំផ្តៅការពារធូលី', 17.50, 'Handwoven breathable mesh food cover protecting baked goods and fruit.'],
                    ['Bamboo Desk Pen Cup & Phone Dock', 'ប្រអប់ដាក់ប៊ិច និងទូរស័ព្ទឫស្សី', 11.00, 'Clean desk caddy milled from solid laminated bamboo.'],
                ],
            ],

            // 9. Fashion & Shoes (17)
            [
                'category' => 'fashion-shoes',
                'brand' => 'sovanna-atelier',
                'shop' => 'SOVANNA',
                'variant_mode' => 'apparel',
                'origin_note' => 'Everyday clothing and footwear tailored or stocked for Cambodia’s warm climate.',
                'items' => [
                    ['Breathable Phnom Penh Linen Button Shirt', 'អាវសាច់ក្រណាត់ឡេនីន', 26.00, 'Airy short-sleeve linen shirt designed for tropical humidity.'],
                    ['Relaxed Tropical Chino Shorts', 'ខោខ្លីកប្បាសបុរស', 19.50, 'Stretch cotton twill shorts with deep pockets for weekend wear.'],
                    ['Lightweight Everyday Oxford Long Sleeve', 'អាវដៃវែងអកស្វឺដ', 29.00, 'Crisp collared cotton shirt suitable for office and dinner outings.'],
                    ['Flowy Midi Cotton Sundress', 'រ៉ូបវែងកប្បាស', 32.00, 'Sleeveless breathable sundress with side pockets and relaxed waist.'],
                    ['Wide-Leg Palazzo Tropical Trousers', 'ខោជើងធំនារី', 27.00, 'Cooling woven viscose trousers suited for warm commuting days.'],
                    ['Classic Pique Cotton Polo Shirt', 'អាវយឺតកបើក', 18.00, 'Combed cotton polo holding its collar shape through regular washes.'],
                    ['UV-Shield Lightweight Zip Hoodie', 'អាវការពារកំដៅថ្ងៃ', 24.50, 'Packable moto-commuter jacket shielding arms during midday rides.'],
                    ['Stretch Denim Straight Jeans', 'ខោខូវប៊យជើងត្រង់', 34.00, 'Medium-weight stretch denim tailored for comfort on scooters and desks.'],
                    ['Battambang Canvas Low Sneakers', 'ស្បែកជើងប៉ាតាក្រណាត់', 28.00, 'Vulcanized rubber sole canvas sneakers built for dry and wet sidewalks.'],
                    ['Mekong Cushioned Walking Sandals', 'ស្បែកជើងសង្រែកទន់', 21.00, 'Adjustable hook-and-loop straps with molded EVA arch footbed.'],
                    ['All-Weather Waterproof Commuter Boots', 'ស្បែកជើងការពារទឹកភ្លៀង', 25.00, 'Molded matte rain boots keeping feet dry during monsoon downpours.'],
                    ['Minimalist Faux-Leather Office Loafers', 'ស្បែកជើងប៉ូលីអូហ្វីស', 36.00, 'Slip-on cushioned work loafers finished with anti-slip rubber outsole.'],
                    ['Everyday Crossbody Nylon Sling Bag', 'កាបូបស្ពាយចំហៀង', 19.00, 'Water-resistant daily sling fitting phone, wallet, keys, and passport.'],
                    ['Structured Canvas Weekender Tote', 'កាបូបក្រណាត់ខ្នាតធំ', 23.00, 'Heavyweight cotton canvas tote with reinforced shoulder webbing.'],
                    ['Wide-Brim Packable Sun Hat', 'មួកការពារកំដៅថ្ងៃ', 12.00, 'Crinkle-resistant woven sun hat folding cleanly into a handbag.'],
                    ['Breathable No-Show Cotton Socks (5 pairs)', 'ស្រោមជើងកប្បាសខ្លី', 8.50, 'Silicone-heel grip cotton socks tailored for warm weather sneakers.'],
                    ['Monsoon Compact Foldable Rain Poncho', 'អាវភ្លៀងបត់បាន', 14.50, 'Full-coverage reflective seam raincoat storing in a glovebox pouch.'],
                ],
            ],

            // 10. Electronics & Accessories (17)
            [
                'category' => 'electronics-accessories',
                'brand' => 'anker-imported',
                'shop' => 'PPTECH',
                'variant_mode' => 'tech',
                'origin_note' => 'Imported official international model sold and supported locally by Phnom Penh Tech Hub.',
                'items' => [
                    ['Imported Galaxy A55 5G Smartphone', 'ទូរស័ព្ទ Samsung Galaxy A55 (នាំចូល)', 389.00, 'Imported 5G smartphone featuring 120Hz AMOLED display and OIS camera, sold in Phnom Penh.'],
                    ['Imported Redmi Note 13 Pro Smartphone', 'ទូរស័ព្ទ Xiaomi Redmi Note 13 Pro (នាំចូល)', 279.00, 'Imported international edition smartphone with fast charging adapter included.'],
                    ['Imported iPad 10th Gen Wi-Fi Tablet', 'ថេប្លេត Apple iPad (នាំចូល)', 369.00, 'Imported international tablet ideal for university notes, retail POS, and media.'],
                    ['Imported WH-CH720N Noise Canceling Headphones', 'កាសឥតខ្សែ Sony Noise Canceling (នាំចូល)', 129.00, 'Imported lightweight Bluetooth over-ear headphones with multipoint pairing.'],
                    ['Imported Soundcore Liberty Wireless Earbuds', 'កាសប្ល៊ូធូស Anker Soundcore (នាំចូល)', 59.00, 'Imported true-wireless earbuds with 40-hour case battery and clear calls.'],
                    ['Imported PowerCore 20000mAh PD Power Bank', 'ថ្មជំនួយ Anker 20000mAh (នាំចូល)', 42.00, 'Imported high-capacity USB-C fast charging battery pack for travel.'],
                    ['Imported 65W 3-Port GaN Wall Charger', 'ឆ្នាំងសាកលឿន GaN 65W (នាំចូល)', 34.00, 'Imported compact UK/US-compatible GaN charger powering laptop and phone simultaneously.'],
                    ['Imported Braided USB-C to USB-C Cable (2m)', 'ខ្សែសាក USB-C 100W (នាំចូល)', 12.00, 'Imported double-braided 100W PD charging and data cable.'],
                    ['Imported MX Anywhere Compact Wireless Mouse', 'ម៉ៅឥតខ្សែ Logitech (នាំចូល)', 64.00, 'Imported multi-surface wireless mouse connecting up to three computers.'],
                    ['Imported Pebble Keys 2 Wireless Keyboard', 'ក្តារចុចឥតខ្សែ Logitech (នាំចូល)', 44.00, 'Imported slim silent Bluetooth keyboard for desk and tablet setups.'],
                    ['Imported 7-in-1 Aluminum USB-C Hub', 'ឧបករណ៍បំបែករន្ធ USB-C HDMI (នាំចូល)', 29.00, 'Imported multiport adapter adding 4K HDMI, SD reader, and USB-A ports.'],
                    ['Imported Smart Band 8 Fitness Tracker', 'នាឡិកាឆ្លាត Xiaomi Band (នាំចូល)', 35.00, 'Imported lightweight AMOLED activity band tracking steps, sleep, and workouts.'],
                    ['Imported 1080p Autofocus Desk Webcam', 'កាមេរ៉ាប្រជុំ 1080p (នាំចូល)', 48.00, 'Imported plug-and-play USB webcam with dual noise-reducing microphones.'],
                    ['Imported Foldable Aluminum Laptop Stand', 'ជើងទម្រឡែបថបអាលុយមីញ៉ូម (នាំចូល)', 21.00, 'Imported ergonomic vented laptop riser improving airflow in warm rooms.'],
                    ['Imported 6-Outlet Surge Protector Strip', 'ព្រីភ្លើងការពាររលកអគ្គិសនី (នាំចូល)', 19.50, 'Imported grounded extension bar with overload switch and USB-A ports.'],
                    ['Imported Portable Bluetooth Mini Speaker', 'ធុងបាសប្ល៊ូធូសតូច (នាំចូល)', 39.00, 'Imported IP67 splash-proof wireless speaker with 12-hour playtime.'],
                    ['Imported Dual-Bay 1TB External SSD', 'ថាសផ្ទុកទិន្នន័យ SSD ចល័ត (នាំចូល)', 89.00, 'Imported shock-resistant USB 3.2 solid-state drive for fast backups.'],
                ],
            ],

            // 11. Beauty & Personal Care (16)
            [
                'category' => 'beauty-personal-care',
                'brand' => 'tonle-botanicals',
                'shop' => 'TONLE',
                'variant_mode' => 'capacity',
                'origin_note' => 'Everyday personal care formulated for warm humid climates.',
                'items' => [
                    ['Lemongrass & Lime Refreshing Body Wash', 'សាប៊ូដុសខ្លួនស្លឹកគ្រៃ', 7.50, 'Cooling botanical cleanser leaving skin clean without heavy residue.'],
                    ['Pure Cold-Pressed Coconut Hair Serum', 'ប្រេងដូងថែរក្សាសក់', 8.90, 'Lightweight tropical hair oil taming frizz and dry ends.'],
                    ['Moringa & Aloe Soothing Gel Moisturizer', 'ជែលផ្តល់សំណើមម្រុំ និងប្រទាលកន្ទុយក្រពើ', 9.50, 'Oil-free hydrating face gel absorbing rapidly in hot weather.'],
                    ['Turmeric & Rice Bran Facial Scrub', 'ស្ក្រាប់លាងមុខកន្ទក់អង្ករ', 8.20, 'Fine rice bran exfoliating polish brightening dull complexion gently.'],
                    ['Daily Invisible UV Defense Sunscreen SPF50', 'ឡេការពារកំដៅថ្ងៃ SPF50', 12.50, 'Non-greasy water-light UV lotion suited for daily outdoor commutes.'],
                    ['Jasmine Blossom Hand Cream Tube', 'ឡេលាបដៃផ្កាម្លិះ', 5.80, 'Fast-absorbing hand lotion lightly scented with night-blooming jasmine.'],
                    ['Kaffir Lime Clarifying Shampoo', 'សាប៊ូកក់សក់ក្រូចសើច', 8.40, 'Invigorating herbal scalp cleanser removing oil and styling buildup.'],
                    ['Coconut Milk Nourishing Conditioner', 'ក្រែមបន្ទន់សក់ខ្ទិះដូង', 8.60, 'Silicone-free hydrating conditioner softening sun-exposed hair strands.'],
                    ['Kampot Sea Salt Mineral Bath Soak', 'អំបិលត្រាំខ្លួនកំពត', 7.90, 'Aromatic mineral soak blended with dried lemongrass essential oil.'],
                    ['Natural Beeswax Lip Balm Stick', 'ក្រែមលាបបបូរមាត់ឃ្មុំ', 3.50, 'Unscented protective balm preventing dry lips in air-conditioned rooms.'],
                    ['Charcoal & Bamboo Pore Cleanser Bar', 'សាប៊ូដុំធ្យូងឫស្សី', 4.80, 'Mild activated charcoal cleansing bar balanced for combination skin.'],
                    ['Nivea Imported Extra Bright Body Lotion', 'ឡេលាបខ្លួន Nivea (នាំចូល)', 7.20, 'Imported lightweight daily body lotion with vitamin C and UV filters.'],
                    ['Alcohol-Free Herbal Pocket Hand Mist', 'ស្ព្រាយបាញ់ដៃសម្លាប់មេរោគ', 3.20, 'Refillable travel sanitizer mist infused with lemongrass hydrosol.'],
                    ['Frangipani Botanical Body Mist', 'ទឹកអប់ខ្លួនផ្កាចំប៉ី', 11.00, 'Delicate daytime floral mist inspired by Cambodian temple gardens.'],
                    ['Reusable Cotton Facial Cleansing Pads (12 pcs)', 'សំឡីជូតមុខកប្បាសប្រើឡើងវិញ', 6.90, 'Washable bamboo-cotton rounds packed in a mesh laundry pouch.'],
                    ['Bamboo Handle Soft Bristle Toothbrush Trio', 'ច្រាសដុសធ្មេញដងឫស្សី', 5.40, 'Three smooth bamboo toothbrushes with charcoal-infused soft bristles.'],
                ],
            ],

            // 12. Home & Kitchen (16)
            [
                'category' => 'home-kitchen',
                'brand' => 'phsar-home',
                'shop' => 'SENSOK',
                'variant_mode' => 'material_size',
                'origin_note' => 'Practical home and kitchen goods stocked locally in Phnom Penh.',
                'items' => [
                    ['1.8L Family Steamed Rice Cooker', 'ឆ្នាំងដាំបាយអគ្គិសនី 1.8L', 34.00, 'Non-stick removable inner pot rice cooker with automatic keep-warm mode.'],
                    ['Heavy Stone Mortar & Pestle (Tbalk)', 'ត្បាល់បុកគ្រឿងថ្ម', 18.50, 'Coarse granite mortar crafted for crushing Khmer kroeung and papaya salad.'],
                    ['Stainless Steel 3-Tier Lunch Tiffin', 'ចានស្រាក់ដែកអ៊ីណុក ៣ជាន់', 14.00, 'Leak-resistant stacked meal carrier keeping rice and soup separate.'],
                    ['Lock&Lock Imported Glass Meal Prep Set (4 pcs)', 'ប្រអប់កែវទុកអាហារ Lock&Lock (នាំចូល)', 22.00, 'Imported borosilicate airtight containers safe for microwave and freezer.'],
                    ['Vacuum Insulated Stainless Flask (1L)', 'ដបទឹករក្សាកំដៅ 1L', 15.50, 'Double-wall stainless bottle keeping ice water cold all afternoon.'],
                    ['Glazed Ceramic Khmer Noodle Bowl Set (4 pcs)', 'ចានគុយទាវសេរ៉ាមិច ៤ចាន', 19.00, 'Deep stoneware bowls suited for kuy teav, samlor, and rice dishes.'],
                    ['Non-Stick Granite Wok Pan (32cm)', 'ខ្ទះឆាទំហំ 32cm', 26.50, 'Flat-bottom induction-ready wok with tempered glass lid.'],
                    ['300TC Breathable Cotton Fitted Sheet Set', 'ឈុតកម្រាលពូកកប្បាស', 38.00, 'Cooling four-piece bedsheet and pillowcase set for tropical nights.'],
                    ['Fine-Mesh Foldable Mosquito Bed Canopy', 'មុងការពារមូសបត់បាន', 24.00, 'Pop-up breathable mosquito tent with dual zippered entry doors.'],
                    ['Ceramic Lotus Tea Pot & 4 Cup Set', 'ឈុតកំសៀវ និងកែវតែសេរ៉ាមិច', 27.00, 'Clean matte white ceramic tea service with bamboo tray accent.'],
                    ['Stackable Clear Pantry Spice Jars (6 pcs)', 'កែវទុកគ្រឿងទេស ៦កែវ', 11.50, 'Glass airtight jars sized for Kampot pepper, palm sugar, and tea leaves.'],
                    ['Rechargeable Quiet Desk Fan USB-C', 'កង្ហារតុសាកថ្ម USB-C', 17.00, 'Four-speed oscillating compact fan running up to 10 hours on battery.'],
                    ['Cotton Waffle Bath Towel Set (2 pcs)', 'កន្សែងពោះគោកប្បាស', 18.00, 'Fast-drying textured cotton bath towels staying fresh in humid bathrooms.'],
                    ['Hardwood Cutting Board with Juice Groove', 'ជ្រុញឈើរឹង', 13.50, 'Thick reversible kitchen prep board gentle on chef knives.'],
                    ['Stainless Fine-Mesh Tea & Broth Strainer', 'តម្រងដែកអ៊ីណុក', 6.50, 'Deep bowl strainer skimming herbs and coffee grounds cleanly.'],
                    ['Modular Plastic & Bamboo Closet Drawer Bin', 'ប្រអប់ដាក់សម្លៀកបំពាក់', 14.50, 'Covered dust-proof wardrobe storage box keeping folded kramas tidy.'],
                ],
            ],

            // 13. Books & Stationery (16)
            [
                'category' => 'books-stationery',
                'brand' => 'pannha-press',
                'shop' => 'PANNHA',
                'variant_mode' => 'edition',
                'origin_note' => 'Educational materials and everyday stationery curated for students and professionals.',
                'items' => [
                    ['Beginner Khmer Script & Reading Workbook', 'សៀវភៅហាត់អាន និងសរសេរអក្សរខ្មែរ', 11.50, 'Step-by-step consonant, vowel, and pronunciation practice with audio QR guide.'],
                    ['Khmer–English Illustrated Everyday Dictionary', 'វចនានុក្រមខ្មែរ-អង់គ្លេសមានរូបភាព', 16.00, 'Practical 3,000-word visual dictionary for daily conversation and travel.'],
                    ['Cambodian Home Cooking Recipe Compendium', 'សៀវភៅរូបមន្តម្ហូបខ្មែរ', 22.00, 'Hardcover photo guide covering 60 regional curries, salads, and desserts.'],
                    ['Architecture of Angkor Illustrated Guide', 'សៀវភៅស្ថាបត្យកម្មប្រាសាទអង្គរ', 24.50, 'Detailed temple floor plans, historical timelines, and photography.'],
                    ['Folktales of the Mekong Bilingual Edition', 'រឿងនិទានប្រជាប្រិយខ្មែរ (ពីរភាសា)', 12.80, 'Parallel Khmer and English classic folktales with ink illustrations.'],
                    ['B5 Lay-Flat Dotted Grid Hardcover Notebook', 'សៀវភៅកំណត់ហេតុ B5 ក្រដាសក្រាស់', 8.90, '120gsm fountain-pen friendly ivory paper bound in sharp linen cover.'],
                    ['A4 Recycled Kraft Study Exercise Books (5 pack)', 'សៀវភៅសរសេរសិស្ស A4 (៥ក្បាល)', 6.50, 'Ruled student notebooks stitched cleanly for school and university terms.'],
                    ['Quick-Dry Gel Ink Roller Pens 0.5mm (10 pack)', 'ប៊ិចជែល 0.5mm (១០ដើម)', 5.20, 'Smooth black and blue smear-resistant pens for clean note-taking.'],
                    ['Pastel Dual-Tip Chisel Highlighters (6 pack)', 'ហ្វឺតហាយឡាយ ៦ពណ៌', 4.80, 'Soft eye-friendly highlighter markers that avoid bleeding through thin pages.'],
                    ['Bamboo & Aluminum Architect Scale Ruler Set', 'ឈុតបន្ទាត់គណិតវិទ្យា និងស្ថាបត្យកម្ម', 9.50, 'Precision metric scale ruler, protractor, and metal compass kit.'],
                    ['Water-Resistant A4 Document Zipper Folder', 'សឺមីដាក់ឯកសារ A4 ការពារទឹក', 4.50, 'Mesh-reinforced PVC document pouch holding certificates and invoices cleanly.'],
                    ['Desktop Sticky Note & Index Tab Organizer', 'ក្រដាសកំណត់ចំណាំលើតុ', 5.90, 'Assorted pastel memo pads and page flags in a refillable desk tray.'],
                    ['Khmer Keyboard Shortcut & Layout Stickers', 'ស្ទីគ័រអក្សរខ្មែរសម្រាប់ក្តារចុច', 3.50, 'Matte non-peel Khmer Unicode keycaps overlays for dark and light keyboards.'],
                    ['Waterproof Acrylic Paint Markers (12 colors)', 'ហ្វឺតគំនូរ ១២ពណ៌', 11.00, 'Opaque pigment pens suitable for paper, wood craft, and ceramic DIY.'],
                    ['Canvas Roll-Up Pencil & Brush Case', 'កាបូបរុំដាក់ប៊ិចក្រណាត់', 7.80, '24-slot durable cotton canvas organizer rolling neatly into a backpack.'],
                    ['Monthly Desk Planner & Budget Pad (2026)', 'ប្រតិទិនផែនការលើតុ', 7.20, 'Tear-off A3 monthly grid calendar with weekly priority checklists.'],
                ],
            ],

            // 14. Sports & Outdoor (16)
            [
                'category' => 'sports-outdoor',
                'brand' => 'decathlon-imported',
                'shop' => 'BBSHOES',
                'variant_mode' => 'sport',
                'origin_note' => 'Imported sports and outdoor gear stocked locally by E-KHMER retail partners.',
                'items' => [
                    ['6mm High-Grip TPE Yoga & Exercise Mat', 'កម្រាលហាត់យូហ្គា TPE 6mm', 21.00, 'Closed-cell sweat-resistant mat paired with a woven carry strap.'],
                    ['Neoprene Coated Hex Dumbbells (Pair)', 'ដុំដែកហាត់ប្រាណស្រោមជ័រ', 28.00, 'Anti-roll hexagonal hand weights ideal for home strength training.'],
                    ['Fabric Resistance Loop Bands (3 Levels)', 'ខ្សែយឺតហាត់ប្រាណ ៣កម្រិត', 13.50, 'Non-slip woven cotton-elastic bands for warmups and mobility.'],
                    ['Vented Cycling Commuter Helmet', 'មួកសុវត្ថិភាពជិះកង់', 29.00, 'Lightweight polycarbonate shell with dial fit adjustment and rear reflector.'],
                    ['USB Rechargeable Bike Head & Tail Light Set', 'ភ្លើងបំភ្លឺកង់សាកថ្ម USB', 14.50, 'IPX5 waterproof front beam and bright red rear flasher for dusk rides.'],
                    ['25L Waterproof Rain-Cover Daypack', 'កាបូបស្ពាយដើរព្រៃ 25L', 34.00, 'Breathable mesh back panel backpack including a stowable rain shield.'],
                    ['1L Tritan BPA-Free Hydration Bottle', 'ដបទឹកកីឡា 1L', 11.50, 'Wide-mouth sports bottle with hourly intake markers and flip lock.'],
                    ['Lightweight Compact Microfiber Gym Towel', 'កន្សែងហាត់ប្រាណស្ងួតលឿន', 9.20, 'High-absorbency quick-dry towel packing tiny into gym bags.'],
                    ['Competition Shuttlecock Badminton Tube (12 pcs)', 'សីវាយបាល់ស្លាប ១២គ្រាប់', 16.00, 'Durable duck-feather shuttlecocks tested for indoor and outdoor courts.'],
                    ['Carbon-Alloy Beginner Badminton Racket Pair', 'រ៉ាកែតវាយសី ១គូ', 32.00, 'Pre-strung lightweight rackets supplied in a zippered shoulder carry case.'],
                    ['Size 5 Machine-Stitched Match Football', 'បាល់ទាត់ខ្នាតលេខ ៥', 19.50, 'Textured PU outer match ball suited for turf and community pitches.'],
                    ['Two-Person Quick-Pitch Tropical Camping Tent', 'តង់បោះជំរុំសម្រាប់ ២នាក់', 54.00, 'Double-door ventilated mesh tent built for Kirirom and Cardamom hills.'],
                    ['Ultralight Parachute Travel Hammock', 'អង្រឹងបោះជំរុំស្រាល', 18.00, 'Quick-drying ripstop nylon hammock complete with tree straps and carabiners.'],
                    ['360ml Waterproof Running Waist Belt', 'ខ្សែក្រវាត់រត់ហាត់ប្រាណ', 12.00, 'Bounce-free stretch belt holding smartphone, keys, and cash securely.'],
                    ['Steel Speed Jump Rope with Bearings', 'ខ្សែលោតកីឡាហ្វីតណេស', 8.90, 'Adjustable coated cable jump rope for cardio and warmups.'],
                    ['Compact Aluminum Trekking Poles (Pair)', 'ឈើច្រត់ឡើងភ្នំអាលុយមីញ៉ូម', 26.00, 'Telescoping cork-grip hiking poles folding neatly onto a daypack.'],
                ],
            ],

            // 15. Baby & Family Products (16)
            [
                'category' => 'baby-family-products',
                'brand' => 'pigeon-imported',
                'shop' => 'TONLE',
                'variant_mode' => 'baby',
                'origin_note' => 'Family and infant essentials stocked with local delivery across Cambodia.',
                'items' => [
                    ['Pigeon Imported Wide-Neck PPSU Feeding Bottle (240ml)', 'ដបទឹកដោះគោទារក Pigeon (នាំចូល)', 14.50, 'Imported heat-resistant PPSU bottle with soft anti-colic silicone nipple.'],
                    ['Organic Cotton Muslin Baby Swaddle Trio', 'កន្សែងរុំទារកកប្បាសម៉ដ្ឋ ៣ផ្ទាំង', 19.50, 'Breathable open-weave muslin wraps keeping infants cool in warm weather.'],
                    ['Pure Water Unscented Baby Wipes (3x80 sheets)', 'ក្រដាសសើមទារកគ្មានក្លិន', 6.80, '99% purified water thick soft wipes gentle for newborn diaper changes.'],
                    ['Soft Silicone Baby Suction Bowl & Spoon', 'ចាន និងស្លាបព្រាស៊ីលីកូនទារក', 11.50, 'Food-grade stay-put suction bowl with flexible gum-safe weaning spoon.'],
                    ['Gentle Chamomile Tear-Free Baby Wash (500ml)', 'សាប៊ូកក់សក់ និងដុសខ្លួនទារក', 8.90, 'pH-balanced botanical cleanser leaving baby skin calm and soft.'],
                    ['Foldable Waterproof Diaper Changing Mat', 'កម្រាលផ្លាស់កន្ទបទារកការពារទឹក', 9.50, 'Compact padded travel mat wiping clean in seconds after outings.'],
                    ['Breathable Mesh Baby Carrier Hip Seat', 'ខ្សែពរទារកមានកៅអីអង្គុយ', 29.00, 'Ergonomic multi-position carrier dispersing weight comfortably on parent shoulders.'],
                    ['Silicone Fruit Feeder Pacifier Pair', 'ក្បាលដោះបឺតផ្លែឈើទារក', 6.40, 'Mesh-free easy-wash perforated silicone bulb for fresh banana or mango.'],
                    ['Non-Contact Digital Forehead Thermometer', 'ទែម៉ូម៉ែត្រស្កេនកម្តៅឌីជីថល', 18.00, 'Fast 1-second silent infrared reading with fever color alert screen.'],
                    ['Electric Steam Baby Bottle Sterilizer', 'ម៉ាស៊ីនសម្លាប់មេរោគដបទឹកដោះគោ', 38.00, 'Six-bottle compact countertop sterilizer finishing cycle in 10 minutes.'],
                    ['Cotton Hooded Animal Baby Bath Towel', 'កន្សែងជូតខ្លួនទារកមានមួក', 13.00, 'Soft terry cotton towel drying newborn hair and shoulders warmly.'],
                    ['Soft-Tip Infant Nail & Grooming Kit', 'ឈុតកាត់ក្រចក និងថែទាំទារក', 8.50, 'Rounded-tip safety scissors, glass file, and soft goat-bristle hairbrush.'],
                    ['Washable Waterproof Cotton Bib Set (5 pcs)', 'កន្សែងជញ្ជប់មាត់ទារក ៥ផ្ទាំង', 9.00, 'Snap-fastened two-layer cotton bibs catching drool and purees cleanly.'],
                    ['Non-Slip Baby Bathtub Folding Support', 'កម្រាលទ្រទារកងូតទឹកបត់បាន', 21.00, 'Cushioned quick-dry sling resting securely inside standard family tubs.'],
                    ['Wooden Alphabet & Animal Stacking Blocks', 'ដុំឈើលេងអក្សរ និងសត្វ', 15.50, 'Smooth water-based painted hardwood blocks encouraging toddler motor skills.'],
                    ['Multi-Pocket Stroller Diaper Organizer Bag', 'កាបូបដាក់សម្ភារៈទារក', 27.00, 'Insulated bottle pockets and wide main zip compartment in wipe-clean nylon.'],
                ],
            ],
        ];

        foreach ($groups as $group) {
            foreach ($group['items'] as [$name, $khmer, $price, $summary]) {
                $products[] = [
                    'name' => $name,
                    'khmer' => $khmer,
                    'category' => $group['category'],
                    'brand' => $group['brand'],
                    'shop' => $group['shop'],
                    'price' => (float) $price,
                    'variant_mode' => $group['variant_mode'],
                    'summary' => $summary,
                    'origin_note' => $group['origin_note'],
                ];
            }
        }

        return $products;
    }
}
