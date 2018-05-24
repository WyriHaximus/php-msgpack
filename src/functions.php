<?php declare(strict_types=1);

use MessagePack\MessagePack;

function msgpack_pack($data)
{
    return MessagePack::pack($data);
}

function msgpack_unpack($msg)
{
    return MessagePack::unpack($msg);
}
