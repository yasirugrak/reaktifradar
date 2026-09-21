<?php

namespace App\Enerjisa\Http;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReactiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $today = CarbonImmutable::now('Europe/Istanbul');
        $this->mergeIfMissing([
            'period' => 'daily', 'start' => $today->startOfMonth()->format('Y-m-d'),
            'end' => $today->format('Y-m-d'), 'status' => 'all',
        ]);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'installation' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'meter' => ['nullable', 'string', 'max:128'],
            'period' => ['required', Rule::in(['hourly', 'daily', 'monthly'])],
            'start' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['all', 'alert', 'invalid'])],
            'refresh' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $from = CarbonImmutable::parse($this->input('start'));
            $to = CarbonImmutable::parse($this->input('end'));
            $limit = $this->input('period') === 'hourly' ? 31 : 366;
            if ($from->diffInDays($to) >= $limit) {
                $validator->errors()->add('end', 'Bu görünümde en fazla '.$limit.' gün seçebilirsiniz.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'end.after_or_equal' => 'Bitiş tarihi başlangıçtan önce olamaz.',
            'before_or_equal' => 'Gelecekteki bir tarih seçemezsiniz.',
            'date_format' => 'Geçerli bir tarih girin.',
        ];
    }
}
