<?php declare(strict_types=1);

use MessagePack\Packer;
use MessagePack\Unpacker;

function msgpack_pack($data)
{
    return (new Packer())->pack($data);
}

function msgpack_unpack($msg)
{
    return (new Unpacker())->unpack($msg);
}
