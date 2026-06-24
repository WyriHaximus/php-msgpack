<?php

declare(strict_types=1);

use MessagePack\MessagePack;

function msgpack_pack(mixed $data): string
{
    return MessagePack::pack($data);
}

function msgpack_unpack(string $msg): mixed
{
    return MessagePack::unpack($msg);
}
