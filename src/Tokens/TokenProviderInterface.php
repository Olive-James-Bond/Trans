<?php

namespace Olive_James_Bond\GoogleTranslate\Tokens;


interface TokenProviderInterface
{

    public function generateToken(string $source, string $target, string $text): string;
}
