<?php

declare(strict_types=1);

namespace VGBridgeTests\Support;

class FakeRestRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $body = '',
        private array $headers = []
    ) {
    }

    public function get_body(): string
    {
        return $this->body;
    }

    public function get_header(string $name): ?string
    {
        $key = strtolower($name);

        return $this->headers[$key] ?? null;
    }

    public function with_header(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = $value;

        return $clone;
    }
}
