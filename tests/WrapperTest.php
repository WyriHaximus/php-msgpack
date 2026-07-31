<?php

declare(strict_types=1);

namespace WyriHaximus\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use WyriHaximus\Msgpack\Bin;

use function current;
use function file_get_contents;
use function gettype;
use function hex2bin;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function key;
use function msgpack_bin;
use function msgpack_pack;
use function msgpack_unpack;
use function str_replace;
use function strcmp;
use function strlen;

use const DIRECTORY_SEPARATOR;
use const PHP_INT_MAX;

final class WrapperTest extends TestCase
{
    /** @return iterable<string, array{title: non-falsy-string, input: mixed, possibilities: list<string>}> */
    public static function provideData(): iterable
    {
        $msgPacktestSuiteString = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'msgpack-test-suite.json');
        if (! is_string($msgPacktestSuiteString)) {
            throw new RuntimeException('Unable to load msgpack test suite, expected string, got "' . gettype($msgPacktestSuiteString) . '"');
        }

        $decoded = json_decode($msgPacktestSuiteString, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Unable to load msgpack test suite, expected array.');
        }

        /** @var array<string, list<mixed>> $json */
        $json = $decoded;

        foreach ($json as $fileName => $fileItems) {
            foreach ($fileItems as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $type = key($item);
                if (! is_string($type)) {
                    continue;
                }

                if ($type === 'msgpack' || in_array($type, ['timestamp', 'ext'], true)) {
                    continue;
                }

                $msgpack = $item['msgpack'] ?? null;
                if (! is_array($msgpack)) {
                    continue;
                }

                $possibleResults = [];
                foreach ($msgpack as $possibleResult) {
                    if (! is_string($possibleResult)) {
                        continue;
                    }

                    $bin = hex2bin(str_replace('-', '', $possibleResult));
                    if (! is_string($bin)) {
                        continue;
                    }

                    $possibleResults[] = $bin;
                }

                $title = $fileName . ' #' . $index . '(' . $type . ')';

                yield $title => [
                    'title' => $title,
                    'input' => self::normalizeInput($fileName, $index, $type, current($item)),
                    'possibilities' => $possibleResults,
                ];
            }
        }
    }

    /** @param list<string> $possibilities */
    #[DataProvider('provideData')]
    #[Test]
    public function pack(string $title, mixed $input, array $possibilities): void
    {
        self::assertContains(msgpack_pack($input), $possibilities, $title);
    }

    /** @param list<string> $possibilities */
    #[DataProvider('provideData')]
    #[Test]
    public function unpack(string $title, mixed $input, array $possibilities): void
    {
        $expected = self::normalizeExpected($input);

        foreach ($possibilities as $key => $value) {
            $possibilities[$key] = msgpack_unpack($value);
        }

        self::assertContains($expected, $possibilities, $title);
    }

    private static function normalizeExpected(mixed $value): mixed
    {
        if ($value instanceof Bin) {
            return $value->data;
        }

        if ($value instanceof stdClass) {
            return [];
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $nestedValue) {
            $normalized[$key] = self::normalizeExpected($nestedValue);
        }

        return $normalized;
    }

    private static function normalizeInput(string $fileName, int $index, string $type, mixed $value): mixed
    {
        return match ($type) {
            'binary' => msgpack_bin(self::decodeBinary($value)),
            'bignum' => self::normalizeBignum($value),
            'map' => self::normalizeMapValue($fileName, $index, $value),
            'array' => self::normalizeArrayValue($fileName, $index, $value),
            default => $value,
        };
    }

    private static function decodeBinary(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        $binary = hex2bin(str_replace('-', '', $value));

        return is_string($binary) ? $binary : '';
    }

    private static function compareDecimalStrings(string $left, string $right): int
    {
        $leftLength  = strlen($left);
        $rightLength = strlen($right);

        if ($leftLength !== $rightLength) {
            return $leftLength <=> $rightLength;
        }

        return strcmp($left, $right);
    }

    private static function normalizeBignum(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if ($value[0] === '-') {
            return (int) $value;
        }

        if (self::compareDecimalStrings($value, (string) PHP_INT_MAX) <= 0) {
            return (int) $value;
        }

        return $value;
    }

    private static function normalizeMapValue(string $fileName, int $index, mixed $value): mixed
    {
        if ($fileName === '41.map.yaml' && $index === 0 && $value === []) {
            return new stdClass();
        }

        if ($fileName === '42.nested.yaml' && $index === 2) {
            return self::arrayToMap($value, MapEmptyMode::AsMap);
        }

        if ($fileName === '42.nested.yaml' && $index === 3) {
            return self::arrayToMap($value, MapEmptyMode::AsArray);
        }

        return self::arrayToMap($value, MapEmptyMode::Inherit);
    }

    private static function normalizeArrayValue(string $fileName, int $index, mixed $value): mixed
    {
        if ($fileName === '42.nested.yaml' && $index === 1 && is_array($value)) {
            return [new stdClass()];
        }

        return $value;
    }

    private static function arrayToMap(mixed $value, MapEmptyMode $emptyMode): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return new stdClass();
        }

        $map = [];
        foreach ($value as $key => $nestedValue) {
            if ($emptyMode === MapEmptyMode::AsMap && is_array($nestedValue) && $nestedValue === []) {
                $map[$key] = new stdClass();

                continue;
            }

            if ($emptyMode === MapEmptyMode::AsArray && is_array($nestedValue)) {
                $map[$key] = $nestedValue;

                continue;
            }

            $map[$key] = is_array($nestedValue)
                ? self::arrayToMap($nestedValue, $emptyMode)
                : $nestedValue;
        }

        return $map;
    }
}
