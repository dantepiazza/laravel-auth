<?php

namespace DantePiazza\LaravelAuth\DTOs;

class DeviceData {
    public function __construct(
        public ?string $fingerprint,
        public ?string $uuid,
        public ?string $model,
        public ?string $platform,
        public ?string $os,
        public ?string $useragent,
        public array $raw = []
    ) {}

    public static function fromHeaders(array $headers): self {
        return new self(
            fingerprint: $headers['x-device-fingerprint'][0] ?? null,
            uuid:        $headers['x-device-uuid'][0] ?? null,
            model:       $headers['x-device-model'][0] ?? null,
            platform:    $headers['x-device-platform'][0] ?? null,
            os:          $headers['x-device-os'][0] ?? null,
            useragent:   $headers['x-device-useragent'][0] ?? null,
        );
    }
}