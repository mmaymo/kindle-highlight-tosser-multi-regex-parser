# kindle-highlight-tosser-multi-regex-parser
Parser for the kindle-highlight-tosser to use with spanish, italian, portuguese, german, french, dutch and english.

## Getting started
This extension allows you to import Kindle clippings with the kindle-highlight-tosser of the following languages:
* Spanish
* Italian
* English
* Dutch
* French
* Portuguese
* German

It parses also clipping files with multiple languages. 

Some edge cases have been handled; if title and author are not formatted as usual (title (author)) this will result in a title field that holds all information available and unknown author.

To use as an extension for the kindle-hightlight-tosser create the `di-ext.php` file inside the `config` folder, and inside it the following code:
```php
<?php

declare(strict_types=1);

use KHTMultiRegexParser\MultipleLangRegexParser;
use KindleHighlightTosser\Infrastructure\Parser\DataSourceParser;
use KindleHighlightTosser\Infrastructure\Parser\MyClippings\MyClippingsParser;
use KindleHighlightTosser\Infrastructure\Parser\MyClippings\RawClippingParser;
use Psr\Container\ContainerInterface;

$sleekDbStoreDirPath = __DIR__ . '/../sleekdb';

return [
    RawClippingParser::class =>
        fn() => new MultipleLangRegexParser(),
    DataSourceParser::class =>
        fn(ContainerInterface $c) => new MyClippingsParser(
            $c->get(RawClippingParser::class)
        ),
];
```

