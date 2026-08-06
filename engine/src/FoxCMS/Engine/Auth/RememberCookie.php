<?php

declare(strict_types=1);

namespace FoxCMS\Engine\Auth;

use \HttpRequest;

/** Central remember-cookie policy shared by login and session restoration. */
final class RememberCookie
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly string $name,
    ) {
    }

    public function value(): ?string
    {
        return $this->request->cookie($this->name);
    }

    public function set(string $token, int $expiresAt): void
    {
        setcookie($this->name, $token, $this->options($expiresAt));
    }

    public function clear(): void
    {
        setcookie($this->name, '', $this->options(time() - 3600));
    }

    /** @return array{expires:int,path:string,secure:bool,httponly:bool,samesite:string} */
    private function options(int $expiresAt): array
    {
        return [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => $this->request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
