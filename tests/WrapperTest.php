<?php declare(strict_types=1);

namespace WyriHaximus\Tests;

use PHPUnit\Framework\TestCase;

final class WrapperTest extends TestCase
{
    public function provideData()
    {
        /** @var array $json */
        $json = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'msgpack-test-suite.json'), true);
        foreach ($json as $fileName => $fileItems) {
            foreach ($fileItems as $index => $item) {
                if (in_array(key($item), ['timestamp', 'ext'], true)) {
                    continue;
                }

                $possibleResults = [];
                foreach ($item['msgpack'] as $possibleResult) {
                    $possibleResults[] = hex2bin(str_replace('-', '', $possibleResult));
                }
                yield [
                    $fileName . ' #' . $index . '(' . key($item) . ')',
                    current($item),
                    $possibleResults,
                ];
            }
        }
    }

    /**
     * @dataProvider provideData
     * @param string $title
     * @param mixed  $input
     * @param array  $possibilities
     */
    public function testPack(string $title, $input, array $possibilities)
    {
        self::assertTrue(in_array(msgpack_pack($input), $possibilities, true), $title);
    }

    /**
     * @dataProvider provideData
     * @param string $title
     * @param mixed  $input
     * @param array  $possibilities
     */
    public function testUnpack(string $title, $input, array $possibilities)
    {
        foreach ($possibilities as $key => $value) {
            $possibilities[$key] = msgpack_unpack($value);
        }
        self::assertTrue(in_array($input, $possibilities, true), $title);
    }
}
