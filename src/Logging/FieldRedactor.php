<?php

declare(strict_types=1);

namespace Lararoxy\Logging;

class FieldRedactor
{
    protected const REDACTED = '***REDACTED***';

    /**
     * @param  array<string>  $headerNames  Headers to redact (case-insensitive)
     * @param  array<string>  $fieldNames  Body fields to redact (dot notation supported)
     */
    public function __construct(
        protected array $headerNames = [],
        protected array $fieldNames = [],
    ) {}

    /**
     * Redact sensitive headers.
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public function redactHeaders(array $headers): array
    {
        $lowered = array_map('strtolower', $this->headerNames);

        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $lowered, true)) {
                $headers[$name] = self::REDACTED;
            }
        }

        return $headers;
    }

    /**
     * Redact sensitive body fields.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function redactBody(array $body): array
    {
        return $this->redactRecursive($body, $this->fieldNames);
    }

    protected function redactRecursive(array $data, array $fields): array
    {
        foreach ($data as $key => &$value) {
            if (in_array($key, $fields, true)) {
                $value = self::REDACTED;
            } elseif (is_array($value)) {
                $value = $this->redactRecursive($value, $fields);
            }
        }

        return $data;
    }
}
