<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Support;

use Illuminate\Http\UploadedFile;

/**
 * A photo queued on a PendingReview, resolved and attached to the review's photos bucket
 * inside the create transaction (before ReviewCreated dispatches).
 */
final readonly class PendingPhoto
{
    public const TYPE_FILE = 'file';

    public const TYPE_URL = 'url';

    public const TYPE_DISK = 'disk';

    public const TYPE_DRAFT = 'draft';

    public function __construct(
        public string $type,
        public UploadedFile|string $value,
        public ?string $disk = null,
    ) {}

    public static function file(UploadedFile|string $file): self
    {
        return new self(self::TYPE_FILE, $file);
    }

    public static function url(string $url): self
    {
        return new self(self::TYPE_URL, $url);
    }

    public static function disk(string $path, ?string $disk = null): self
    {
        return new self(self::TYPE_DISK, $path, $disk);
    }

    public static function draft(string $token): self
    {
        return new self(self::TYPE_DRAFT, $token);
    }
}
