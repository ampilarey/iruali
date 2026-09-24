<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Marketplace categories for shops across the Maldives (English + Dhivehi).
     */
    public function run(): void
    {
        $categories = [
            ['slug' => 'food-groceries', 'en' => 'Food & Groceries', 'dv' => 'ކާބޯތަކެތި',
                'desc_en' => 'Local produce, fish, spices and pantry staples from island kitchens.', 'desc_dv' => 'ރަށްރަށުން ގެނެވޭ ކާބޯތަކެތި، މަސް އަދި ހަވާދު.'],
            ['slug' => 'handmade-crafts', 'en' => 'Handmade & Crafts', 'dv' => 'އަތުން ހަދާ ތަކެތި',
                'desc_en' => 'Lacquer work, woven mats, coir rope and gifts made by Maldivian artisans.', 'desc_dv' => 'ލާއްޖެހުން، ތުންޑުކުނާ، ރޯނު އަދި ހަދިޔާ ތަކެތި.'],
            ['slug' => 'fashion', 'en' => 'Fashion', 'dv' => 'ހެދުން',
                'desc_en' => 'Clothing, libaas, sarongs and accessories.', 'desc_dv' => 'ހެދުން، ލިބާސް، ފޭރާން އަދި އެކްސެސަރީޒް.'],
            ['slug' => 'home-living', 'en' => 'Home & Living', 'dv' => 'ގޭގެ ތަކެތި',
                'desc_en' => 'Furniture, kitchenware and everything for island homes.', 'desc_dv' => 'ފަރުނީޗަރު، ބަދިގޭ ތަކެތި އަދި ގެއަށް ބޭނުންވާ ތަކެތި.'],
            ['slug' => 'electronics', 'en' => 'Electronics', 'dv' => 'އިލެކްޓްރޯނިކްސް',
                'desc_en' => 'Phones, accessories, fans and solar gear.', 'desc_dv' => 'ފޯނު، އެކްސެސަރީޒް، ފަންކާ އަދި ސޯލާ ތަކެތި.'],
            ['slug' => 'beauty-wellness', 'en' => 'Beauty & Wellness', 'dv' => 'ރީތިކަމާއި ސިއްހަތު',
                'desc_en' => 'Coconut oil, natural skincare and wellness products.', 'desc_dv' => 'ކާށިތެޔޮ، ހަމުގެ ބޭސް އަދި ސިއްހީ ތަކެތި.'],
            ['slug' => 'fishing-marine', 'en' => 'Fishing & Marine', 'dv' => 'މަސްވެރިކަމާއި ކަނޑު',
                'desc_en' => 'Fishing lines, snorkel gear and boat supplies.', 'desc_dv' => 'މަސްވެރިކަމުގެ ތަކެތި، ސްނޯކަލް އަދި ދޯނީގެ ތަކެތި.'],
            ['slug' => 'kids-baby', 'en' => 'Kids & Baby', 'dv' => 'ކުޑަކުދިން',
                'desc_en' => 'Toys, school supplies and baby essentials.', 'desc_dv' => 'ކުޅޭ ތަކެތި، ސްކޫލް ތަކެތި އަދި ތުއްތު ކުދިންގެ ތަކެތި.'],
        ];

        foreach ($categories as $data) {
            Category::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => ['en' => $data['en'], 'dv' => $data['dv']],
                    'description' => ['en' => $data['desc_en'], 'dv' => $data['desc_dv']],
                    'status' => 'active',
                ]
            );
        }

        $this->command?->info('✅ '.count($categories).' marketplace categories ready');
    }
}
