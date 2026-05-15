<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function reportTitle(): ?string
    {
        $title = trim($this->string('title')->toString());

        return $title === '' ? null : $title;
    }
}
