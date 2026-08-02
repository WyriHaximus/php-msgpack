# MessagePack polyfill

[![Latest Stable Version](https://poser.pugx.org/WyriHaximus/msgpack/v/stable.png)](https://packagist.org/packages/WyriHaximus/msgpack)
[![Total Downloads](https://poser.pugx.org/WyriHaximus/msgpack/downloads.png)](https://packagist.org/packages/WyriHaximus/msgpack)
[![License](https://poser.pugx.org/WyriHaximus/msgpack/license.png)](https://packagist.org/packages/WyriHaximus/msgpack)

# Install

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require wyrihaximus/msgpack
```

[`ext-msgpack`](https://github.com/msgpack/msgpack-php) polyfill with a native PHP implementation.

# Why this package instead of `rybakit/msgpack`?

This package used to delegate to [`rybakit/msgpack`](https://github.com/rybakit/msgpack.php). That library is a capable, general-purpose MessagePack implementation, but its defaults and API are built for MessagePack itself — not for mimicking PHP's `ext-msgpack` functions.

This rewrite targets **`ext-msgpack` compatibility** specifically:

- **Drop-in polyfill** — `msgpack_pack()` and `msgpack_unpack()` behave like the extension. No `Packer`/`Unpacker` options, transformers, or extension types to configure.
- **Validated against the test suite** — the full [`msgpack-test-suite`](https://github.com/kawanet/msgpack-test-suite) runs in CI (118 pack/unpack cases covering nil, bool, numbers, strings, binary, arrays, maps, and nesting).
- **Correct PHP semantics** — packing matches `ext-msgpack` rules that `rybakit/msgpack` gets wrong out of the box, including:
  - `stdClass` for empty maps (not empty PHP arrays)
  - integer encoding for numeric strings in the bignum range
  - uint64 handling above `PHP_INT_MAX` (int → float → decimal string on unpack)
  - str8 string encoding with bin fallbacks where the extension expects them
- **Minimal footprint** — a small native implementation with only `ext-bcmath` required. No third-party runtime dependencies.
- **Simple static API** — `WyriHaximus\Msgpack\Packer::pack()` and `WyriHaximus\Msgpack\Unpacker::unpack()` when you want classes instead of global functions.

If you need a full MessagePack toolkit (streaming, custom extensions, timestamps, `CanBePacked` objects, explicit map/array mode flags), `rybakit/msgpack` remains the better choice. If you need **`msgpack_pack()` / `msgpack_unpack()` to work without the C extension installed**, this package is the better fit.

# Usage

Exactly the same as [`ext-msgpack`](https://github.com/msgpack/msgpack-php):

```php
<?php
$data = array(0=>1,1=>2,2=>3);
$msg = msgpack_pack($data);
$data = msgpack_unpack($msg);
```

Or via the static entry points:

```php
<?php
use WyriHaximus\Msgpack\Packer;
use WyriHaximus\Msgpack\Unpacker;

$msg  = Packer::pack($data);
$data = Unpacker::unpack($msg);
```

Binary values that must be packed as MessagePack `bin` (not `str`) can be wrapped with `msgpack_bin()`:

```php
<?php
$msg = msgpack_pack(msgpack_bin("\x00\xff"));
```

# License

The MIT License (MIT)

Copyright (c) 2026 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

