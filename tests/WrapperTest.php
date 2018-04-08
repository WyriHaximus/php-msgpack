<?php declare(strict_types=1);

namespace WyriHaximus\Tests;

use PHPUnit\Framework\TestCase;

final class WrapperTest extends TestCase
{
    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideData
     * @param mixed $title
     * @param mixed $raw
     * @param mixed $packed
     */
    public function testPack(string $title, $raw, string $packed)
    {
        self::assertEquals($packed, msgpack_pack($raw), $title);
    }

    /**
     * @dataProvider \MessagePack\Tests\DataProvider::provideData
     * @param mixed $title
     * @param mixed $raw
     * @param mixed $packed
     */
    public function testUnpack(string $title, $raw, string $packed)
    {
        self::assertEquals($raw, msgpack_unpack($packed), $title);
    }
}
