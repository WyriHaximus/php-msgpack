<?php

declare(strict_types=1);

namespace WyriHaximus\Msgpack;

use InvalidArgumentException;
use stdClass;

use function array_is_list;
use function bcdiv;
use function bcmod;
use function chr;
use function count;
use function get_object_vars;
use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function pack;
use function preg_match;
use function strcmp;
use function strlen;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class Packer
{
    private const string UTF8_REGEX = '/\A(?:
          [\x00-\x7F]++
        | [\xC2-\xDF][\x80-\xBF]
        |  \xE0[\xA0-\xBF][\x80-\xBF]
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
        |  \xED[\x80-\x9F][\x80-\xBF]
        |  \xF0[\x90-\xBF][\x80-\xBF]{2}
        | [\xF1-\xF3][\x80-\xBF]{3}
        |  \xF4[\x80-\x8F][\x80-\xBF]{2}
        )*+\z/x';

    public static function pack(mixed $value): string
    {
        return new self()->packValue($value);
    }

    private function packValue(mixed $value): string
    {
        if ($value instanceof Bin) {
            return $this->packBin($value->data);
        }

        if ($value === null) {
            return "\xc0";
        }

        if (is_bool($value)) {
            return $value ? "\xc3" : "\xc2";
        }

        if (is_int($value)) {
            return $this->packInt($value);
        }

        if (is_float($value)) {
            return $this->packDouble($value);
        }

        if (is_string($value)) {
            if ($this->isInt64String($value)) {
                return $this->packInt((int) $value);
            }

            if ($this->isUint64String($value)) {
                return $this->packUint64($value);
            }

            return $this->packString($value);
        }

        if ($value instanceof stdClass) {
            return $this->packMapObject($value);
        }

        if (is_array($value)) {
            if ($value === [] || array_is_list($value)) {
                return $this->packArray($value);
            }

            return $this->packMap($value);
        }

        if (is_object($value)) {
            throw new InvalidArgumentException('Unsupported object type: ' . $value::class);
        }

        throw new InvalidArgumentException('Unsupported type: ' . gettype($value));
    }

    private function packInt(int $int): string
    {
        if ($int >= 0) {
            if ($int <= 0x7f) {
                return chr($int);
            }

            if ($int <= 0xff) {
                return "\xcc" . chr($int);
            }

            if ($int <= 0xffff) {
                return "\xcd" . chr($int >> 8) . chr($int & 0xff);
            }

            if ($int <= 0xffffffff) {
                return pack('CN', 0xce, $int);
            }

            return pack('CJ', 0xcf, $int);
        }

        if ($int >= -0x20) {
            return chr(0xe0 | ($int & 0xff));
        }

        if ($int >= -0x80) {
            return "\xd0" . chr($int & 0xff);
        }

        if ($int >= -0x8000) {
            return "\xd1" . chr(($int >> 8) & 0xff) . chr($int & 0xff);
        }

        if ($int >= -0x80000000) {
            return pack('CN', 0xd2, $int);
        }

        return pack('CJ', 0xd3, $int);
    }

    private function packUint64(string $decimal): string
    {
        $bytes = '';
        $value = $decimal;

        for ($i = 0; $i < 8; ++$i) {
            $remainder = bcmod($value, '256');
            $bytes     = chr((int) $remainder) . $bytes;
            $value     = bcdiv($value, '256', 0);
        }

        return "\xcf" . $bytes;
    }

    private function isInt64String(string $value): bool
    {
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            return false;
        }

        if ($value[0] === '-') {
            return $this->compareDecimalStrings($value, (string) PHP_INT_MIN) >= 0;
        }

        return $this->compareDecimalStrings($value, (string) PHP_INT_MAX) <= 0;
    }

    private function isUint64String(string $value): bool
    {
        if ($value === '' || $value[0] === '-') {
            return false;
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            return false;
        }

        return $this->compareDecimalStrings($value, (string) PHP_INT_MAX) > 0
            && $this->compareDecimalStrings($value, '18446744073709551615') <= 0;
    }

    private function compareDecimalStrings(string $left, string $right): int
    {
        $leftLength  = strlen($left);
        $rightLength = strlen($right);

        if ($leftLength !== $rightLength) {
            return $leftLength <=> $rightLength;
        }

        return strcmp($left, $right);
    }

    private function packDouble(float $float): string
    {
        return "\xcb" . pack('E', $float);
    }

    private function packString(string $string): string
    {
        if ($string === '' || preg_match(self::UTF8_REGEX, $string) === 1) {
            return $this->packStr($string);
        }

        return $this->packBin($string);
    }

    private function packStr(string $string): string
    {
        $length = strlen($string);

        if ($length < 32) {
            return chr(0xa0 | $length) . $string;
        }

        if ($length <= 0xff) {
            return "\xd9" . chr($length) . $string;
        }

        if ($length <= 0xffff) {
            return "\xda" . chr($length >> 8) . chr($length & 0xff) . $string;
        }

        return pack('CN', 0xdb, $length) . $string;
    }

    private function packBin(string $string): string
    {
        $length = strlen($string);

        if ($length <= 0xff) {
            return "\xc4" . chr($length) . $string;
        }

        if ($length <= 0xffff) {
            return "\xc5" . chr($length >> 8) . chr($length & 0xff) . $string;
        }

        return pack('CN', 0xc6, $length) . $string;
    }

    /** @param list<mixed> $array */
    private function packArray(array $array): string
    {
        $size = count($array);
        $data = $this->packArrayHeader($size);

        foreach ($array as $value) {
            $data .= $this->packValue($value);
        }

        return $data;
    }

    private function packArrayHeader(int $size): string
    {
        if ($size <= 0x0f) {
            return chr(0x90 | $size);
        }

        if ($size <= 0xffff) {
            return "\xdc" . chr($size >> 8) . chr($size & 0xff);
        }

        return pack('CN', 0xdd, $size);
    }

    /** @param array<int|string, mixed> $map */
    private function packMap(array $map): string
    {
        $size = count($map);
        $data = $this->packMapHeader($size);

        foreach ($map as $key => $value) {
            if (is_int($key)) {
                $data .= $this->packInt($key);
            } else {
                $data .= $this->packString($key);
            }

            $data .= $this->packValue($value);
        }

        return $data;
    }

    private function packMapObject(stdClass $object): string
    {
        /** @var array<string, mixed> $properties */
        $properties = get_object_vars($object);

        return $this->packMap($properties);
    }

    private function packMapHeader(int $size): string
    {
        if ($size <= 0x0f) {
            return chr(0x80 | $size);
        }

        if ($size <= 0xffff) {
            return "\xde" . chr($size >> 8) . chr($size & 0xff);
        }

        return pack('CN', 0xdf, $size);
    }
}
