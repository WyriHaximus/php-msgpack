<?php

declare(strict_types=1);

namespace WyriHaximus\Msgpack;

use InvalidArgumentException;
use UnexpectedValueException;

use function array_key_exists;
use function bcadd;
use function bccomp;
use function bcmul;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function ord;
use function sprintf;
use function strlen;
use function substr;
use function unpack;

use const PHP_INT_MAX;

final class Unpacker
{
    private int $offset = 0;

    public static function unpack(string $buffer): mixed
    {
        return new self()->unpackBuffer($buffer);
    }

    private function unpackBuffer(string $buffer): mixed
    {
        $this->offset = 0;

        return $this->unpackNext($buffer);
    }

    private function unpackNext(string $buffer): mixed
    {
        $byte = $this->readByte($buffer);

        if ($byte <= 0x7f) {
            return $byte;
        }

        if ($byte >= 0xe0) {
            return $byte - 0x100;
        }

        return match ($byte) {
            0xc0 => null,
            0xc2 => false,
            0xc3 => true,
            0xc4 => $this->unpackBin($buffer, $this->readUint8($buffer)),
            0xc5 => $this->unpackBin($buffer, $this->readUint16($buffer)),
            0xc6 => $this->unpackBin($buffer, $this->readUint32($buffer)),
            0xcc => $this->readUint8($buffer),
            0xcd => $this->readUint16($buffer),
            0xce => $this->readUint32($buffer),
            0xcf => $this->unpackUint64($buffer),
            0xd0 => $this->unpackInt8($buffer),
            0xd1 => $this->unpackInt16($buffer),
            0xd2 => $this->unpackInt32($buffer),
            0xd3 => $this->unpackInt64($buffer),
            0xca => $this->unpackFloat32($buffer),
            0xcb => $this->unpackFloat64($buffer),
            0xd9 => $this->unpackStr($buffer, $this->readUint8($buffer)),
            0xda => $this->unpackStr($buffer, $this->readUint16($buffer)),
            0xdb => $this->unpackStr($buffer, $this->readUint32($buffer)),
            0xdc => $this->unpackArray($buffer, $this->readUint16($buffer)),
            0xdd => $this->unpackArray($buffer, $this->readUint32($buffer)),
            0xde => $this->unpackMap($buffer, $this->readUint16($buffer)),
            0xdf => $this->unpackMap($buffer, $this->readUint32($buffer)),
            default => $this->unpackFix($buffer, $byte),
        };
    }

    private function unpackFix(string $buffer, int $byte): mixed
    {
        if (($byte & 0xe0) === 0xa0) {
            return $this->unpackStr($buffer, $byte & 0x1f);
        }

        if (($byte & 0xf0) === 0x90) {
            return $this->unpackArray($buffer, $byte & 0x0f);
        }

        if (($byte & 0xf0) === 0x80) {
            return $this->unpackMap($buffer, $byte & 0x0f);
        }

        throw new UnexpectedValueException(sprintf('Invalid MessagePack byte 0x%02x at offset %d.', $byte, $this->offset - 1));
    }

    private function readByte(string $buffer): int
    {
        if ($this->offset >= strlen($buffer)) {
            throw new InvalidArgumentException('Unexpected end of MessagePack data.');
        }

        return ord($buffer[$this->offset++]);
    }

    private function readBytes(string $buffer, int $length): string
    {
        if ($length < 0 || strlen($buffer) < $this->offset + $length) {
            throw new InvalidArgumentException('Unexpected end of MessagePack data.');
        }

        $bytes         = substr($buffer, $this->offset, $length);
        $this->offset += $length;

        return $bytes;
    }

    private function readUint8(string $buffer): int
    {
        return $this->readByte($buffer);
    }

    private function readUint16(string $buffer): int
    {
        return $this->readUnsignedInt($this->readBytes($buffer, 2), 'n');
    }

    private function readUint32(string $buffer): int
    {
        return $this->readUnsignedInt($this->readBytes($buffer, 4), 'N');
    }

    private function unpackInt8(string $buffer): int
    {
        $value = $this->readByte($buffer);

        return $value > 0x7f ? $value - 0x100 : $value;
    }

    private function unpackInt16(string $buffer): int
    {
        $value = $this->readUnsignedInt($this->readBytes($buffer, 2), 'n');

        return $value > 0x7fff ? $value - 0x10000 : $value;
    }

    private function unpackInt32(string $buffer): int
    {
        $value = $this->readUnsignedInt($this->readBytes($buffer, 4), 'N');

        return $value > 0x7fffffff ? $value - 0x100000000 : $value;
    }

    private function unpackInt64(string $buffer): int
    {
        return $this->readSignedInt($this->readBytes($buffer, 8), 'J');
    }

    private function unpackUint64(string $buffer): int|string|float
    {
        $bytes   = $this->readBytes($buffer, 8);
        $decimal = $this->uint64BytesToDecimal($bytes);

        if (bccomp($decimal, (string) PHP_INT_MAX) <= 0) {
            return (int) $decimal;
        }

        if (bccomp($decimal, '4503599627370496') <= 0) {
            return (float) $decimal;
        }

        return $decimal;
    }

    /** @return numeric-string */
    private function uint64BytesToDecimal(string $bytes): string
    {
        $decimal = '0';

        for ($i = 0; $i < 8; ++$i) {
            $decimal = bcmul($decimal, '256');
            $decimal = bcadd($decimal, (string) ord($bytes[$i]));
        }

        return $decimal;
    }

    private function unpackFloat32(string $buffer): float
    {
        return $this->readFloat($this->readBytes($buffer, 4), 'G');
    }

    private function unpackFloat64(string $buffer): float
    {
        return $this->readFloat($this->readBytes($buffer, 8), 'E');
    }

    private function unpackStr(string $buffer, int $length): string
    {
        return $this->readBytes($buffer, $length);
    }

    private function unpackBin(string $buffer, int $length): string
    {
        return $this->readBytes($buffer, $length);
    }

    /** @return list<mixed> */
    private function unpackArray(string $buffer, int $size): array
    {
        $array = [];

        for ($i = 0; $i < $size; ++$i) {
            $array[] = $this->unpackNext($buffer);
        }

        return $array;
    }

    /** @return array<int|string, mixed> */
    private function unpackMap(string $buffer, int $size): array
    {
        $map = [];

        for ($i = 0; $i < $size; ++$i) {
            $key   = $this->unpackNext($buffer);
            $value = $this->unpackNext($buffer);

            if (! is_int($key) && ! is_string($key)) {
                throw new UnexpectedValueException('MessagePack map keys must be integers or strings.');
            }

            $map[$key] = $value;
        }

        return $map;
    }

    private function readUnsignedInt(string $bytes, string $format): int
    {
        $unpacked = unpack($format, $bytes);

        if (! is_array($unpacked) || ! array_key_exists(1, $unpacked) || ! is_int($unpacked[1])) {
            throw new InvalidArgumentException('Unable to unpack unsigned integer.');
        }

        return $unpacked[1];
    }

    private function readSignedInt(string $bytes, string $format): int
    {
        $unpacked = unpack($format, $bytes);

        if (! is_array($unpacked) || ! array_key_exists(1, $unpacked) || ! is_int($unpacked[1])) {
            throw new InvalidArgumentException('Unable to unpack signed integer.');
        }

        return $unpacked[1];
    }

    private function readFloat(string $bytes, string $format): float
    {
        $unpacked = unpack($format, $bytes);

        if (! is_array($unpacked) || ! array_key_exists(1, $unpacked) || ! is_float($unpacked[1])) {
            throw new InvalidArgumentException('Unable to unpack float.');
        }

        return $unpacked[1];
    }
}
