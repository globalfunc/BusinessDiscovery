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
            'ai_token_cap' => $this->input('ai_token_cap') === '' ? null : $this->input('ai_token_cap'),
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
            // §7.7/§0.1 per-BO override of config('ai.per_bo_token_cap'); blank = use the global default.
            'ai_token_cap' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
