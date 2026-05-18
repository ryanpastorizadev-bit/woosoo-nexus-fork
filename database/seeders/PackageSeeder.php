<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ModifierDescription;
use App\Models\Package;
use App\Models\PackageModifier;
use App\Models\Krypton\Menu;

/**
 * Seeds the packages and package_modifiers tables from the currently
 * hardcoded Set Meal A / B / C configuration.
 *
 * Requires a live Krypton POS DB connection to resolve modifier
 * Krypton menu IDs from receipt_name codes (P1–P10, B1–B9, C1–C2).
 * Resolves against menu_group_id = 34 (named tablet menus, IDs 114–134).
 * B10 does not exist in Krypton and is excluded.
 *
 * Run with:  php artisan db:seed --class=PackageSeeder
 */
class PackageSeeder extends Seeder
{
    /**
     * The canonical package definitions that were previously hardcoded in
     * Menu::getModifiers() and TabletApiController.
     *
     * krypton_menu_id — the POS menus.id for the package (indicator) row.
     * codes           — receipt_name values of the allowed modifier menus.
     */
    private array $definitions = [
        [
            'name'            => 'Classic Feast',
            'krypton_menu_id' => 46,
            'sort_order'      => 0,
            'description'     => 'Our essential Korean BBQ experience — five signature pork samgyupsal cuts grilled fresh at your table, served with unlimited classic sides. The perfect introduction to authentic Korean barbecue.',
            'codes'           => ['P1', 'P2', 'P3', 'P4', 'P5'],
        ],
        [
            'name'            => 'Noble Selection',
            'krypton_menu_id' => 47,
            'sort_order'      => 1,
            'description'     => 'A step up for the adventurous — all five classic pork samgyupsal cuts plus premium beef woosamgyup and beef bulgogi, with unlimited sides. The best of pork and beef in one feast.',
            'codes'           => ['P1', 'P2', 'P3', 'P4', 'P5', 'B1', 'B2', 'B3'],
        ],
        [
            'name'            => 'Royal Banquet',
            'krypton_menu_id' => 48,
            'sort_order'      => 2,
            'description'     => 'The ultimate feast — our complete spread of ten pork, nine beef, and two chicken specialties grilled at your table with unlimited sides. Everything on the grill, made for sharing.',
            'codes'           => [
                'P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7', 'P8', 'P9', 'P10',
                'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9',
                'C1', 'C2',
            ],
        ],
    ];

    /**
     * Default customer-facing descriptions per modifier receipt_name code.
     * Names sourced from krypton_woosoo_specs.md (menu_group_id = 34).
     * Stored globally per modifier (shared across every package that
     * includes it) via the modifier_descriptions table.
     */
    private array $modifierDescriptions = [
        'P1'  => 'Classic thick-cut pork belly, unseasoned and grilled to a crisp, caramelized finish.',
        'P2'  => 'Pork belly rubbed with a smoky Cajun spice blend for a bold, savory kick.',
        'P3'  => 'Pork belly marinated in sweet-and-spicy Korean yangnyeom sauce.',
        'P4'  => 'Bright citrus and cracked black pepper marinated pork belly.',
        'P5'  => 'Fragrant herb-marinated pork belly with an aromatic, savory finish.',
        'P6'  => 'Pork belly fired up with Korean chili pepper — for lovers of heat.',
        'P7'  => 'Tender pork belly in a nutty toasted-sesame and spicy glaze.',
        'P8'  => 'Premium pork belly seasoned with our signature secret spice blend.',
        'P9'  => 'Pork rolled around golden enoki mushrooms — juicy, savory, and satisfying.',
        'P10' => 'Lean, tender pork neck (moksal) — a leaner cut with deep, rich flavor.',
        'B1'  => 'Thinly sliced beef belly (woosamgyup) that grills in seconds and melts in your mouth.',
        'B2'  => 'Classic soy-marinated beef bulgogi — sweet, savory, and tender.',
        'B3'  => 'Beef belly with Asian green chili for a fresh, spicy bite.',
        'B4'  => 'Beef belly brightened with a zesty citrus marinade.',
        'B5'  => 'Herb-marinated beef belly with a fragrant, aromatic finish.',
        'B6'  => 'Beef belly with bold Korean chili pepper heat.',
        'B7'  => 'Beef belly in a spicy toasted-sesame glaze.',
        'B8'  => 'Beef belly seasoned with our signature secret spice blend.',
        'B9'  => 'Beef rolled around golden enoki mushrooms — rich and tender.',
        'C1'  => 'Dak galbi — Korean spicy stir-fried chicken in a fiery gochujang marinade.',
        'C2'  => 'Tender chicken in a sweet-savory Korean bulgogi marinade.',
    ];

    public function run(): void
    {
        foreach ($this->definitions as $def) {
            $package = Package::updateOrCreate(
                ['krypton_menu_id' => $def['krypton_menu_id']],
                [
                    'name'        => $def['name'],
                    'description' => $def['description'] ?? null,
                    'sort_order'  => $def['sort_order'],
                    'is_active'   => true,
                ]
            );

            $this->command->info("Package '{$def['name']}' (krypton_menu_id={$def['krypton_menu_id']}) upserted.");

            // Resolve modifier IDs from Krypton by receipt_name.
            try {
                $modifierMenus = Menu::whereIn('receipt_name', $def['codes'])
                    ->where('menu_group_id', 34)
                    ->get()
                    ->keyBy('receipt_name');

                if ($modifierMenus->isEmpty()) {
                    $this->command->warn("  No modifier menus found in Krypton for '{$def['name']}'. Skipping modifiers.");
                    continue;
                }

                // Re-seed modifiers: wipe existing then insert in defined order.
                $package->modifiers()->delete();

                foreach ($def['codes'] as $order => $code) {
                    $modifierMenu = $modifierMenus->get($code);
                    if ($modifierMenu) {
                        PackageModifier::create([
                            'package_id'      => $package->id,
                            'krypton_menu_id' => $modifierMenu->id,
                            'sort_order'      => $order,
                        ]);

                        // Global, package-independent default description for this modifier.
                        ModifierDescription::updateOrCreate(
                            ['krypton_menu_id' => $modifierMenu->id],
                            ['description' => $this->modifierDescriptions[$code] ?? null],
                        );
                    } else {
                        $this->command->warn("  Modifier code '{$code}' not found in Krypton — skipped.");
                    }
                }

                $this->command->info("  Seeded {$modifierMenus->count()} modifier(s).");
            } catch (\Throwable $e) {
                $this->command->warn("  Could not seed modifiers for '{$def['name']}': " . $e->getMessage());
                $this->command->warn("  Ensure Krypton POS DB is reachable and run this seeder again.");
            }
        }
    }
}
