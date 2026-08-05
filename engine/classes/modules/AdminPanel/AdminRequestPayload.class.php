<?php

declare(strict_types=1);

/**
 * Decodes structured fields from the normalized administrative request.
 */
final class AdminRequestPayload
{
    public function __construct(
        private array $request,
        private AdminResponder $responder,
    ) {
    }

    public function object(string $field): array
    {
        $value = $this->request[$field] ?? null;
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string)$value, true);
        if (!is_array($decoded)) {
            $this->responder->send([
                'message' => 'Некорректный JSON payload.',
                'type' => 'error',
            ], 400);
        }
        return $decoded;
    }
}
