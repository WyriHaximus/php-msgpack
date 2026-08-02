<?php

declare(strict_types=1);

namespace WyriHaximus\Msgpack;

/**
 * Marker for values that must be packed as MessagePack bin (not str).
 */
final readonly class Bin
{
    public function __construct(
        public string $data,
    ) {
    }

    public static function from(string $data): self
    {
        return new self($data);
    }
}
