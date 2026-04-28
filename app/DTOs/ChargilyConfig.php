<?php

namespace App\DTOs;

final readonly class ChargilyConfig
{
    public function __construct(
        public string $mode,
        public string $apiKey,
        public string $secretKey,
        public string $webhookSecret,
        public string $locale,
        public string $baseUrl
    ){}
   

}