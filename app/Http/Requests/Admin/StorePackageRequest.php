<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'krypton_menu_id' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'modifiers' => ['nullable', 'array'],
            'modifiers.*.krypton_menu_id' => ['required', 'integer', 'min:1'],
            'modifiers.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'modifiers.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
