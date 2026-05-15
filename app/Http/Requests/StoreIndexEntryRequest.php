<?php

namespace App\Http\Requests;

use App\Enums\IndexScope;
use App\Enums\IndexType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIndexEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type' => ['required', Rule::enum(IndexType::class)],
            'served_on' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
            'cycle_code' => [
                Rule::requiredIf($type === IndexType::Formation->value),
                'nullable',
                'regex:/^C\d+$/i',
            ],
            'module_code' => [
                Rule::requiredIf($type === IndexType::Formation->value),
                'nullable',
                'regex:/^M\d+$/i',
            ],
            'title' => [
                Rule::requiredIf($type === IndexType::Formation->value),
                'nullable',
                'string',
                'max:255',
            ],
            'about' => [
                Rule::requiredIf($type === IndexType::SocialApostolate->value),
                'nullable',
                'string',
                'max:255',
            ],
            'scope' => ['nullable', Rule::enum(IndexScope::class)],
            'return_type' => ['nullable', Rule::enum(IndexType::class)],
            'return_scope' => ['nullable', Rule::enum(IndexScope::class)],
            'return_report' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cycle_code.regex' => 'Use the cycle format C1, C2, C3, and so on.',
            'module_code.regex' => 'Use the module format M1, M2, M3, and so on.',
            'time_end.after' => 'The time out must be later than the time in.',
        ];
    }

    public function indexType(): IndexType
    {
        return IndexType::from($this->string('type')->toString());
    }

    public function recordPayload(): array
    {
        $payload = [
            'served_on' => $this->string('served_on')->toString(),
            'time_start' => $this->string('time_start')->toString(),
            'time_end' => $this->string('time_end')->toString(),
        ];

        if ($this->indexType() === IndexType::Formation) {
            $payload['cycle_code'] = strtoupper($this->string('cycle_code')->toString());
            $payload['module_code'] = strtoupper($this->string('module_code')->toString());
            $payload['title'] = trim($this->string('title')->toString());
        }

        if ($this->indexType() === IndexType::SocialApostolate) {
            $payload['about'] = trim($this->string('about')->toString());
        }

        return $payload;
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
