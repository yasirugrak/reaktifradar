<?php

namespace App\Enerjisa\Http;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Account access is enforced by the Enerjisa route middleware.
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['hourly', '1', '2', '3'])],
            'installation' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'month' => ['exclude_unless:kind,hourly', 'required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'from_date' => ['exclude_unless:kind,hourly', 'nullable', 'date_format:Y-m-d\TH:i', 'before:today'],
            'start' => ['exclude_if:kind,hourly', 'required', 'date_format:Y-m-d', $this->input('kind') === '1' ? 'before_or_equal:today' : 'before:today'],
            'end' => ['exclude_if:kind,hourly', 'required', 'date_format:Y-m-d', 'after_or_equal:start', $this->input('kind') === '1' ? 'before_or_equal:today' : 'before:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute alanını doldurun.',
            'date_format' => ':attribute için geçerli bir tarih girin.',
            'before' => 'En geç dünü seçebilirsiniz.',
            'before_or_equal' => 'Gelecek bir tarih seçemezsiniz.',
            'after_or_equal' => 'Bitiş tarihi başlangıçtan önce olamaz.',
            'installation.regex' => 'Tesisat numarası yalnızca harf, rakam, tire ve alt çizgi içerebilir.',
        ];
    }

    public function attributes(): array
    {
        return ['installation' => 'Tesisat', 'start' => 'Başlangıç', 'end' => 'Bitiş', 'month' => 'Ay', 'kind' => 'Veri türü'];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->input('kind') === 'hourly') {
                if ($this->filled('from_date') && substr($this->input('from_date'), 0, 7) !== $this->input('month')) {
                    $validator->errors()->add('from_date', 'Delta başlangıcı seçilen ayın içinde olmalıdır.');
                }

                return;
            }
            $start = CarbonImmutable::parse($this->input('start'));
            $end = CarbonImmutable::parse($this->input('end'));
            $limit = $this->input('kind') === '3' ? $start->addYearNoOverflow() : $start->addMonthNoOverflow();
            // Inclusive end-of-day must be strictly before the next month/year boundary.
            if ($end->greaterThanOrEqualTo($limit)) {
                $validator->errors()->add('end', $this->input('kind') === '3'
                    ? 'Reset sorgusu en fazla 1 yıllık olabilir.'
                    : 'Endeks sorgusu en fazla 1 aylık olabilir.');
            }
        }];
    }

    /** @return array<string, string|int> */
    public function apiParameters(): array
    {
        $data = $this->validated();
        if ($data['kind'] === 'hourly') {
            return [
                'installationNumbers' => $data['installation'],
                'meterMonth' => $data['month'],
                'fromDate' => ! empty($data['from_date'])
                    ? CarbonImmutable::parse($data['from_date'])->format('Y-m-d H:i:s')
                    : $data['month'].'-01 00:00:00',
            ];
        }

        return [
            'installationNumber' => $data['installation'],
            'startDate' => CarbonImmutable::parse($data['start'])->format('d/m/Y').' 00:00:00',
            'endDate' => ($data['kind'] === '1' && $data['end'] === now('Europe/Istanbul')->format('Y-m-d'))
                ? now('Europe/Istanbul')->format('d/m/Y H:i:s') : CarbonImmutable::parse($data['end'])->format('d/m/Y').' 23:59:59',
            'dataType' => (int) $data['kind'],
        ];
    }
}
