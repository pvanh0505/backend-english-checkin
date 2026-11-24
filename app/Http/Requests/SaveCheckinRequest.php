<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Bỏ khoảng trắng thừa và các dòng trống trước khi validate */
    protected function prepareForValidation(): void
    {
        $sentences = collect($this->input('sentences', []))
            ->filter(fn ($s) => is_array($s))
            ->map(fn (array $s) => [
                'content' => trim((string) ($s['content'] ?? '')),
                'meaning' => isset($s['meaning']) ? (trim((string) $s['meaning']) ?: null) : null,
                'note' => isset($s['note']) ? (trim((string) $s['note']) ?: null) : null,
            ])
            ->filter(fn (array $s) => $s['content'] !== '')
            ->values()
            ->all();

        $this->merge([
            'sentences' => $sentences,
            'note' => trim((string) $this->input('note', '')) ?: null,
        ]);
    }

    public function rules(): array
    {
        $min = config('checkin.min_sentences');
        $max = config('checkin.max_sentences');

        return [
            'note' => ['nullable', 'string', 'max:2000'],
            'sentences' => ['required', 'array', "min:{$min}", "max:{$max}"],
            'sentences.*.content' => ['required', 'string', 'max:500', 'distinct:ignore_case'],
            'sentences.*.meaning' => ['nullable', 'string', 'max:500'],
            'sentences.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'sentences.required' => 'Bạn cần ghi lại ít nhất :min câu đã học hôm qua.',
            'sentences.min' => 'Bạn cần ghi lại ít nhất :min câu đã học hôm qua.',
            'sentences.max' => 'Tối đa :max câu cho mỗi lần check-in.',
            'sentences.*.content.distinct' => 'Câu này bị trùng với một câu khác.',
            'sentences.*.content.max' => 'Mỗi câu tối đa 500 ký tự.',
        ];
    }
}
