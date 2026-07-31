<?php

declare(strict_types=1);

use WyriHaximus\Msgpack\Bin;
use WyriHaximus\Msgpack\Packer;
use WyriHaximus\Msgpack\Unpacker;

function msgpack_pack(mixed $data): string
{
    return Packer::pack($data);
}

function msgpack_unpack(string $msg): mixed
{
    return Unpacker::unpack($msg);
}

function msgpack_bin(string $data): Bin
{
    return Bin::from($data);
}
