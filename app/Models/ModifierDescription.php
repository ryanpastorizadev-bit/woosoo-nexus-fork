<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A global, package-independent description for a modifier menu item,
 * keyed by its Krypton POS menus.id. Shared by every package that
 * includes the same modifier.
 */
class ModifierDescription extends Model
{
    protected $fillable = [
        'krypton_menu_id',
        'description',
    ];

    protected $casts = [
        'krypton_menu_id' => 'integer',
    ];
}
