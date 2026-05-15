<?php

use App\Models\MenuImage;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $schema = Schema::connection('pos');

    $schema->dropIfExists('menus');
    $schema->dropIfExists('menu_images');
    $schema->dropIfExists('menu_categories');
    $schema->dropIfExists('menu_groups');
    $schema->dropIfExists('menu_course_types');

    $schema->create('menu_categories', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('name')->nullable();
    });

    $schema->create('menu_groups', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('name')->nullable();
    });

    $schema->create('menu_course_types', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('name')->nullable();
    });

    $schema->create('menus', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->string('name')->nullable();
        $table->string('kitchen_name')->nullable();
        $table->string('receipt_name')->nullable();
        $table->decimal('price', 8, 2)->default(0);
        $table->decimal('cost', 8, 2)->nullable();
        $table->text('description')->nullable();
        $table->boolean('is_taxable')->default(false);
        $table->boolean('is_available')->default(true);
        $table->boolean('is_modifier')->default(false);
        $table->boolean('is_discountable')->default(false);
        $table->boolean('is_modifier_only')->default(false);
        $table->integer('menu_category_id')->nullable();
        $table->integer('menu_group_id')->nullable();
        $table->integer('menu_course_type_id')->nullable();
    });

    $schema->create('menu_images', function (Blueprint $table): void {
        $table->integer('id')->primary();
        $table->integer('menu_id')->nullable();
        $table->string('path')->nullable();
    });

    DB::connection('pos')->table('menu_categories')->insert(['id' => 1, 'name' => 'Meals']);
    DB::connection('pos')->table('menu_groups')->insert(['id' => 1, 'name' => 'Main']);
    DB::connection('pos')->table('menu_course_types')->insert(['id' => 1, 'name' => 'Dinner']);

    DB::connection('pos')->table('menus')->insert([
        [
            'id' => 1,
            'name' => 'A Valid Upload',
            'kitchen_name' => 'A Valid Upload',
            'receipt_name' => 'VALID',
            'price' => 100,
            'is_available' => true,
            'menu_category_id' => 1,
            'menu_group_id' => 1,
            'menu_course_type_id' => 1,
        ],
        [
            'id' => 2,
            'name' => 'B Missing File',
            'kitchen_name' => 'B Missing File',
            'receipt_name' => 'MISSING',
            'price' => 200,
            'is_available' => true,
            'menu_category_id' => 1,
            'menu_group_id' => 1,
            'menu_course_type_id' => 1,
        ],
        [
            'id' => 3,
            'name' => 'C No Upload',
            'kitchen_name' => 'C No Upload',
            'receipt_name' => 'NONE',
            'price' => 300,
            'is_available' => true,
            'menu_category_id' => 1,
            'menu_group_id' => 1,
            'menu_course_type_id' => 1,
        ],
        [
            'id' => 4,
            'name' => 'D Unavailable Menu',
            'kitchen_name' => 'D Unavailable Menu',
            'receipt_name' => 'UNAVAILABLE',
            'price' => 400,
            'is_available' => false,
            'menu_category_id' => 1,
            'menu_group_id' => 1,
            'menu_course_type_id' => 1,
        ],
    ]);
});

test('menus index marks only menus with an existing uploaded image file', function () {
    Storage::fake('public');
    Storage::disk('public')->put('menu/images/valid.jpg', 'image-bytes');

    MenuImage::create([
        'menu_id' => 1,
        'path' => 'menu/images/valid.jpg',
    ]);

    MenuImage::create([
        'menu_id' => 2,
        'path' => 'menu/images/missing.jpg',
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('menus'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Menus/Index')
            ->where('menus.0.id', 1)
            ->where('menus.0.group', 'Main')
            ->where('menus.0.category', 'Meals')
            ->where('menus.0.course', 'Dinner')
            ->where('menus.0.has_uploaded_image', true)
            ->where('menus.1.id', 2)
            ->where('menus.1.has_uploaded_image', false)
            ->where('menus.2.id', 3)
            ->where('menus.2.has_uploaded_image', false)
            ->where('menus.3.id', 4)
            ->where('menus.3.is_available', false)
            ->where('stats.0.value', 4)
            ->where('stats.1.value', 3)
        );
});
