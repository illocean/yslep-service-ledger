<?php

namespace App\Http\Requests;

use App\Enums\IndexType;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $typePattern = implode('|', array_map(fn (IndexType $type): string => preg_quote($type->value, '/'), IndexType::cases()));

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'selected_entries' => ['required', 'array', 'min:1'],
            'selected_entries.*' => ['string', 'regex:/^('.$typePattern.'):\d+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'selected_entries.required' => 'Choose at least one entry before saving a report group.',
            'selected_entries.min' => 'Choose at least one entry before saving a report group.',
        ];
    }

    public function reportTitle(): ?string
    {
        $title = trim($this->string('title')->toString());

        return $title === '' ? null : $title;
    }

    public function selectedEntryMap(): array
    {
        $map = [];

        foreach ($this->input('selected_entries', []) as $value) {
            [$type, $id] = explode(':', (string) $value, 2);

            $map[$type] ??= [];
            $map[$type][] = (int) $id;
        }

        return array_map(fn (array $ids): array => array_values(array_unique($ids)), $map);
    }
}
