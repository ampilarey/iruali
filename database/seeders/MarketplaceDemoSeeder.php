<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo marketplace: six approved local sellers and their products.
 * Idempotent (keyed on seller email and product SKU), so it is safe to re-run.
 *
 *   php artisan db:seed --class=MarketplaceDemoSeeder --force
 */
class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $sellerRole = Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller']);
        $categories = Category::whereIn('slug', [
            'food-groceries', 'handmade-crafts', 'fashion', 'home-living',
            'electronics', 'beauty-wellness', 'fishing-marine', 'kids-baby',
        ])->pluck('id', 'slug');

        if ($categories->count() < 8) {
            $this->call(CategorySeeder::class);
            $categories = Category::pluck('id', 'slug');
        }

        foreach ($this->sellers() as $sellerData) {
            $seller = User::updateOrCreate(
                ['email' => $sellerData['email']],
                [
                    'name' => $sellerData['owner'],
                    'business_name' => $sellerData['shop'],
                    'business_description' => $sellerData['about'],
                    'city' => $sellerData['island'],
                    'state' => $sellerData['atoll'],
                    'country' => 'Maldives',
                    'phone' => $sellerData['phone'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'is_active' => true,
                    'email_verified' => true,
                    'phone_verified' => true,
                    'is_seller' => true,
                    'seller_approved' => true,
                    'seller_approved_at' => now(),
                    'seller_applied_at' => now()->subWeeks(3),
                ]
            );
            $seller->roles()->syncWithoutDetaching([$sellerRole->id]);

            foreach ($sellerData['products'] as $p) {
                Product::updateOrCreate(
                    ['sku' => $p['sku']],
                    [
                        'name' => ['en' => $p['en'], 'dv' => $p['dv']],
                        'description' => ['en' => $p['desc']],
                        'category_id' => $categories[$p['cat']],
                        'seller_id' => $seller->id,
                        'price' => $p['price'],
                        'compare_price' => $p['was'] ?? null,
                        'stock_quantity' => $p['stock'] ?? 25,
                        'reorder_point' => 5,
                        'is_active' => true,
                        'is_featured' => $p['featured'] ?? false,
                    ]
                );
            }
        }

        $this->command?->info('✅ Demo marketplace: '.count($this->sellers()).' sellers and their products');
    }

    protected function sellers(): array
    {
        return [
            [
                'email' => 'islandcrafts@example.com', 'owner' => 'Aishath Rasheed', 'shop' => 'Island Crafts',
                'island' => 'Hithadhoo', 'atoll' => 'Addu City', 'phone' => '7771001',
                'about' => 'Hand-woven thundu kunaa mats and coir work from Addu, made by a family of weavers.',
                'products' => [
                    ['sku' => 'IC-MAT-SM', 'cat' => 'handmade-crafts', 'en' => 'Thundu kunaa mat, small', 'dv' => 'ތުންޑުކުނާ، ކުޑަ', 'price' => 360, 'was' => 450, 'featured' => true,
                        'desc' => 'Hand-woven from dried haa reed in natural and black patterns. About 60 × 90 cm. Each mat takes around two weeks to weave.'],
                    ['sku' => 'IC-MAT-LG', 'cat' => 'handmade-crafts', 'en' => 'Thundu kunaa mat, large', 'dv' => 'ތުންޑުކުނާ، ބޮޑު', 'price' => 1250, 'stock' => 4,
                        'desc' => 'Large hand-woven reed mat, about 120 × 180 cm. A traditional wedding and housewarming gift.'],
                    ['sku' => 'IC-COIR-10', 'cat' => 'handmade-crafts', 'en' => 'Coir rope, 10 m', 'dv' => 'ރޯނު، 10 މީޓަރު', 'price' => 85,
                        'desc' => 'Hand-twisted coconut-fibre rope. Strong, salt-resistant and biodegradable.'],
                ],
            ],
            [
                'email' => 'thulhaadhoo@example.com', 'owner' => 'Ibrahim Waheed', 'shop' => 'Thulhaadhoo Lacquer',
                'island' => 'Thulhaadhoo', 'atoll' => 'Baa Atoll', 'phone' => '7771002',
                'about' => 'Traditional lacquer work (laajehun) turned and painted by hand in Thulhaadhoo.',
                'products' => [
                    ['sku' => 'TL-BOX-SM', 'cat' => 'handmade-crafts', 'en' => 'Lacquer box, small', 'dv' => 'ލާއްޖެހި ފޮށި، ކުޑަ', 'price' => 950, 'featured' => true,
                        'desc' => 'Turned from local wood and finished in red, yellow and black lacquer with a carved floral pattern.'],
                    ['sku' => 'TL-VASE', 'cat' => 'handmade-crafts', 'en' => 'Lacquer vase', 'dv' => 'ލާއްޖެހި ވާސް', 'price' => 1600, 'stock' => 3,
                        'desc' => 'Tall lacquer vase, about 30 cm. A signed piece from the Thulhaadhoo workshop.'],
                ],
            ],
            [
                'email' => 'maafushifresh@example.com', 'owner' => 'Mariyam Shifa', 'shop' => 'Maafushi Fresh',
                'island' => 'Maafushi', 'atoll' => 'Kaafu Atoll', 'phone' => '7771003',
                'about' => 'Island kitchen staples: coconut oil, rihaakuru, dried fish and hand-ground spices.',
                'products' => [
                    ['sku' => 'MF-COIL-500', 'cat' => 'beauty-wellness', 'en' => 'Virgin coconut oil, 500 ml', 'dv' => 'ކާށިތެޔޮ، 500 މލ', 'price' => 120, 'featured' => true,
                        'desc' => 'Cold-pressed from fresh coconuts on Maafushi. For cooking, hair and skin.'],
                    ['sku' => 'MF-RIHA-250', 'cat' => 'food-groceries', 'en' => 'Rihaakuru, 250 g', 'dv' => 'ރިހާކުރު، 250 ގ', 'price' => 95,
                        'desc' => 'Thick fish paste slow-cooked from tuna broth. The base of mas huni and many curries.'],
                    ['sku' => 'MF-VALHOMAS', 'cat' => 'food-groceries', 'en' => 'Valhoamas (smoked tuna), 500 g', 'dv' => 'ވަޅޯމަސް، 500 ގ', 'price' => 180, 'was' => 210,
                        'desc' => 'Smoked and sun-dried skipjack tuna, ready to flake into rice and curries.'],
                    ['sku' => 'MF-CURRY-MIX', 'cat' => 'food-groceries', 'en' => 'Maldivian curry spice mix', 'dv' => 'ރިހަ ހަވާދު', 'price' => 45,
                        'desc' => 'Hand-ground blend of chilli, coriander, cumin, fennel and turmeric.'],
                ],
            ],
            [
                'email' => 'reefline@example.com', 'owner' => 'Hassan Nizam', 'shop' => 'Reefline Marine',
                'island' => 'Malé', 'atoll' => 'Kaafu Atoll', 'phone' => '7771004',
                'about' => 'Fishing and snorkel gear for island life, shipped to every atoll.',
                'products' => [
                    ['sku' => 'RM-HANDLINE', 'cat' => 'fishing-marine', 'en' => 'Hand line fishing kit', 'dv' => 'އަތުން މަސްބާނާ ސެޓް', 'price' => 240,
                        'desc' => 'Reel, 100 m of line, hooks and sinkers for reef and night fishing.'],
                    ['sku' => 'RM-SNORKEL', 'cat' => 'fishing-marine', 'en' => 'Snorkel set, adult', 'dv' => 'ސްނޯކަލް ސެޓް', 'price' => 650, 'was' => 780, 'featured' => true,
                        'desc' => 'Tempered-glass mask, dry-top snorkel and adjustable fins. Sizes S to XL.'],
                    ['sku' => 'RM-DRYBAG', 'cat' => 'fishing-marine', 'en' => 'Dry bag, 20 L', 'dv' => 'ފެން ނުވަދޭ ދަބަސް', 'price' => 320,
                        'desc' => 'Roll-top waterproof bag for boat trips and ferry rides.'],
                ],
            ],
            [
                'email' => 'hulhumalestyle@example.com', 'owner' => 'Fathimath Zeena', 'shop' => 'Hulhumalé Style',
                'island' => 'Hulhumalé', 'atoll' => 'Kaafu Atoll', 'phone' => '7771005',
                'about' => 'Modest everyday fashion and libaas, tailored in Hulhumalé.',
                'products' => [
                    ['sku' => 'HS-LIBAAS', 'cat' => 'fashion', 'en' => 'Embroidered libaas', 'dv' => 'ކަސަބު ލިބާސް', 'price' => 1450, 'featured' => true,
                        'desc' => 'Traditional libaas with hand-embroidered collar (kasabu). Made to your measurements.'],
                    ['sku' => 'HS-SARONG', 'cat' => 'fashion', 'en' => 'Cotton sarong', 'dv' => 'ފޭލި', 'price' => 280, 'was' => 350,
                        'desc' => 'Light cotton sarong in lagoon and sand colours. One size.'],
                    ['sku' => 'HS-KIDS-SET', 'cat' => 'kids-baby', 'en' => 'Kids Eid outfit', 'dv' => 'ކުދިންގެ ޢީދު ހެދުން', 'price' => 520,
                        'desc' => 'Two-piece festive set for ages 2 to 10.'],
                ],
            ],
            [
                'email' => 'solarsouth@example.com', 'owner' => 'Ahmed Shareef', 'shop' => 'Solar South',
                'island' => 'Fuvahmulah', 'atoll' => 'Fuvahmulah City', 'phone' => '7771006',
                'about' => 'Fans, power banks and solar lights for islands where power matters.',
                'products' => [
                    ['sku' => 'SS-SOLAR-LAMP', 'cat' => 'electronics', 'en' => 'Solar garden lamp, 4 pack', 'dv' => 'ސޯލާ ބައްތި، 4', 'price' => 390, 'featured' => true,
                        'desc' => 'Charges in the sun, lights for 8 hours. Salt-air resistant.'],
                    ['sku' => 'SS-FAN-RECH', 'cat' => 'electronics', 'en' => 'Rechargeable table fan', 'dv' => 'ޗާޖުކުރެވޭ ފަންކާ', 'price' => 560, 'was' => 690,
                        'desc' => 'Runs up to 10 hours on one charge. Useful during power cuts.'],
                    ['sku' => 'SS-POWERBANK', 'cat' => 'electronics', 'en' => 'Power bank, 20,000 mAh', 'dv' => 'ޕަވަރ ބޭންކް', 'price' => 450, 'stock' => 0,
                        'desc' => 'Charges a phone four to five times. USB-C in and out.'],
                ],
            ],
        ];
    }
}
