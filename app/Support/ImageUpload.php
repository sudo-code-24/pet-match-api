<?php

namespace App\Support;

final class ImageUpload
{
    public const MAX_KILOBYTES = 5120;

    public const MAX_ERROR_MESSAGE = 'Image must not exceed 5MB';

    private function __construct()
    {
    }
}

