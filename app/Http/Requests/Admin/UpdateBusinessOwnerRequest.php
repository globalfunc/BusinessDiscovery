<?php

namespace App\Http\Requests\Admin;

use App\Enums\BusinessOwnerStatus;
use App\Enums\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'language' => $this->input('language') === '' ? null : $this->input('language'),
            'pre_selected_niche_id' => $this->input('pre_selected_niche_id') === '' ? null : $this->input('pre_selected_niche_id'),
        ]);
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
            'status' => ['required', Rule::enum(BusinessOwnerStatus::class)],
            'pre_selected_niche_id' => ['nullable', 'integer', 'exists:taxonomy_niches,id'],
        ];
    }
}
