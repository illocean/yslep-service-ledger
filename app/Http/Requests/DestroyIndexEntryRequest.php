<?php

namespace App\Http\Requests;

use App\Enums\IndexScope;
use App\Enums\IndexType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DestroyIndexEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(IndexType::class)],
            'scope' => ['nullable', Rule::enum(IndexScope::class)],
        ];
    }

    public function indexType(): IndexType
    {
        return IndexType::from($this->string('type')->toString());
    }

    public function indexRouteParameters(IndexType $defaultType): array
    {
        $type = IndexType::tryFrom($this->string('return_type')->toString()) ?? $defaultType;
        $scope = IndexScope::tryFrom($this->string('return_scope')->toString())
            ?? IndexScope::tryFrom($this->string('scope')->toString())
            ?? IndexScope::All;

        $parameters = [
            'type' => $type->value,
        ];

        if ($scope !== IndexScope::All) {
            $parameters['scope'] = $scope->value;
        }

        $reportTag = $this->string('return_report')->toString();

        if ($scope === IndexScope::Report && $reportTag !== '') {
            $parameters['report'] = $reportTag;
        }

        return $parameters;
    }
}
