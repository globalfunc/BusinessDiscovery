<?php

namespace App\Http\Requests\Admin;

use App\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'greeting_override' => ['nullable', 'string', 'max:2000'],
            'admin_context' => ['nullable', 'string', 'max:5000'],
            'language' => ['nullable', Rule::enum(Language::class)],
        ];
    }
}
