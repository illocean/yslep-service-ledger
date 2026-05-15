<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicYearSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'selected_report_groups' => ['nullable', 'array'],
            'selected_report_groups.*' => ['integer', 'exists:report_groups,id'],
        ];
    }

    public function snapshotTitle(): ?string
    {
        $title = trim($this->string('title')->toString());

        return $title === '' ? null : $title;
    }

    public function academicYear(): ?string
    {
        $academicYear = trim($this->string('academic_year')->toString());

        return $academicYear === '' ? null : $academicYear;
    }

    public function selectedReportGroupIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('selected_report_groups', []))));
    }
}
