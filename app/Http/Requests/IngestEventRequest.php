<?php

namespace App\Http\Requests;

use App\Enums\IssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngestEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([IssueType::Exception->value, IssueType::Log->value])],
            'timestamp' => ['nullable', 'date'],

            // Exception payload
            'exception' => ['required_if:type,exception', 'array'],
            'exception.class' => ['required_if:type,exception', 'string', 'max:255'],
            'exception.message' => ['nullable', 'string'],
            'exception.file' => ['nullable', 'string', 'max:1024'],
            'exception.line' => ['nullable', 'integer'],
            'exception.trace' => ['nullable'],
            'exception.previous' => ['nullable'],

            // Log payload
            'log' => ['required_if:type,log', 'array'],
            'log.channel' => ['required_if:type,log', 'string', 'max:128'],
            'log.level' => ['required_if:type,log', 'string', 'max:16'],
            'log.message' => ['required_if:type,log', 'string'],
            'log.context' => ['nullable', 'array'],

            // Request / environment context
            'context' => ['nullable', 'array'],
            'context.url' => ['nullable', 'string', 'max:2048'],
            'context.method' => ['nullable', 'string', 'max:10'],
            'context.user_id' => ['nullable'],
            'context.ip' => ['nullable', 'string', 'max:64'],
            'context.release' => ['nullable', 'string', 'max:128'],
            'context.environment' => ['nullable', 'string', 'max:64'],

            'breadcrumbs' => ['nullable', 'array'],
        ];
    }
}
