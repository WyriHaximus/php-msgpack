<?php

declare(strict_types=1);

namespace WyriHaximus\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function current;
use function file_get_contents;
use function gettype;
use function hex2bin;
use function in_array;
use function is_string;
use function json_decode;
use function key;
use function msgpack_pack;
use function msgpack_unpack;
use function str_replace;

use const DIRECTORY_SEPARATOR;

final class WrapperTest extends TestCase
{
    /** @return iterable<string, array{title: non-falsy-string, input: mixed, possibilities: list<string>}> */
    public static function provideData(): iterable
    {
        $msgPacktestSuiteString = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'msgpack-test-suite.json');
        if (! is_string($msgPacktestSuiteString)) {
            throw new RuntimeException('Unable to load msgpack test suite, expected string, got "' . gettype($msgPacktestSuiteString) . '"');
        }

        /** @var array<string, array<array{timestamp?: array<int>, ext?: array<mixed>, msgpack: array<string>}>> $json */
        $json = json_decode($msgPacktestSuiteString, true);
        foreach ($json as $fileName => $fileItems) {
            foreach ($fileItems as $index => $item) {
                if (in_array(key($item), ['timestamp', 'ext'], true)) {
                    continue;
                }

                $possibleResults = [];
                foreach ($item['msgpack'] as $possibleResult) {
                    $bin = hex2bin(str_replace('-', '', $possibleResult));
                    if (! is_string($bin)) {
                        continue;
                    }

                    $possibleResults[] = $bin;
                }

                $title = $fileName . ' #' . $index . '(' . key($item) . ')';

                yield $title => [
                    'title' => $title,
                    'input' => current($item),
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
        foreach ($possibilities as $key => $value) {
            $possibilities[$key] = msgpack_unpack($value);
        }

        self::assertContains($input, $possibilities, $title);
    }
}
