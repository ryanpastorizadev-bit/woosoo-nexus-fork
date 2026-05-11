<?php

use App\Models\PackageModifier;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(ConsoleKernel::class);
$kernel->bootstrap();

// Package 46: Classic Feast (P1-P5)
$modifiers_46 = [49, 50, 51, 52, 53];
// Package 47: Noble Selection (P1-P5 + B1-B3)
$modifiers_47 = [49, 50, 51, 52, 53, 54, 55, 56];
// Package 48: Royal Banquet (P1-P5 + B1-B9/B6)
$modifiers_48 = [49, 50, 51, 52, 53, 54, 55, 56, 61, 62, 63, 64, 65, 66];

try {
    DB::transaction(function () use ($modifiers_46, $modifiers_47, $modifiers_48) {
        PackageModifier::truncate();

        foreach ($modifiers_46 as $index => $menu_id) {
            PackageModifier::create([
                'package_id' => 46,
                'menu_id' => $menu_id,
                'position' => $index + 1,
            ]);
        }

        foreach ($modifiers_47 as $index => $menu_id) {
            PackageModifier::create([
                'package_id' => 47,
                'menu_id' => $menu_id,
                'position' => $index + 1,
            ]);
        }

        foreach ($modifiers_48 as $index => $menu_id) {
            PackageModifier::create([
                'package_id' => 48,
                'menu_id' => $menu_id,
                'position' => $index + 1,
            ]);
        }
    });
} catch (Throwable $e) {
    fwrite(STDERR, "❌ Failed to update package modifiers: {$e->getMessage()}\n");
    throw $e;
}

echo "✅ Package 46 (Classic Feast): 5 modifiers added\n";
echo "✅ Package 47 (Noble Selection): 8 modifiers added\n";
echo "✅ Package 48 (Royal Banquet): 14 modifiers added\n";

// Verify
echo "\n📊 VERIFICATION:\n";
echo "Package 46: " . PackageModifier::where('package_id', 46)->count() . " modifiers\n";
echo "Package 47: " . PackageModifier::where('package_id', 47)->count() . " modifiers\n";
echo "Package 48: " . PackageModifier::where('package_id', 48)->count() . " modifiers\n";
echo "Total: " . PackageModifier::count() . " package modifiers in database\n";

// Display details
echo "\n📋 PACKAGE 46 (Classic Feast) Details:\n";
PackageModifier::where('package_id', 46)->with('menu')->orderBy('position')->get()->each(
    fn($pm) => print "  Position {$pm->position}: ID {$pm->menu_id} → {$pm->menu->kitchen_name}\n"
);

echo "\n📋 PACKAGE 47 (Noble Selection) Details:\n";
PackageModifier::where('package_id', 47)->with('menu')->orderBy('position')->get()->each(
    fn($pm) => print "  Position {$pm->position}: ID {$pm->menu_id} → {$pm->menu->kitchen_name}\n"
);

echo "\n📋 PACKAGE 48 (Royal Banquet) Details:\n";
PackageModifier::where('package_id', 48)->with('menu')->orderBy('position')->get()->each(
    fn($pm) => print "  Position {$pm->position}: ID {$pm->menu_id} → {$pm->menu->kitchen_name}\n"
);

echo "\n✅ Update complete!\n";
