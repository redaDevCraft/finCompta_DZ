<?php

namespace App\Services\Ai;

class AiResponseSanitizer
{
    public function clean(string $text): string
    {
        // Mask long digit sequences (NIF/RC/etc.)
        $text = preg_replace('/\b\d{11,16}\b/u', '[masqué]', $text);

        return trim($text);
    }
}