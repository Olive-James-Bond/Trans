<?php

namespace Olive_James_Bond\GoogleTranslate\Tokens;

class SampleTokenGenerator implements TokenProviderInterface
{

    public function generateToken(string $source, string $target, string $text): string
    {
        return sprintf('%d.%d', rand(10000, 99999), rand(10000, 99999));
    }
}
