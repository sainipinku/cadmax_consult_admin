<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Throwable;

class ValidMediaUpload implements ValidationRule
{
    public const KIND_IMAGE = 'image';
    public const KIND_VIDEO = 'video';
    public const KIND_EITHER = 'either';

    public const IMAGE_MIMES = [
        'image/jpeg' => ['jpg','jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif'],
        'image/webp' => ['webp'],
    ];

    public const VIDEO_MIMES = [
        'video/mp4'  => ['mp4'],
        'video/webm' => ['webm'],
        'video/quicktime' => ['mov'],
        'video/3gpp' => ['3gp'],
        'video/x-msvideo' => ['avi'],
    ];

    public function __construct(
        public readonly string $kind = self::KIND_EITHER,
        public readonly ?int $maxMbImage = null,
        public readonly ?int $maxMbVideo = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile) {
            $fail('The :attribute must be a valid file upload.');
            return;
        }
        if (!$value->isValid()) {
            $fail('The :attribute file is corrupted or incomplete.');
            return;
        }

        $sniffed = $value->getMimeType();
        $ext = strtolower((string) $value->getClientOriginalExtension());

        $allowedImages = self::IMAGE_MIMES;
        $allowedVideos = self::VIDEO_MIMES;

        $kind = null;
        if (isset($allowedImages[$sniffed])) {
            $kind = self::KIND_IMAGE;
            $allowed = $allowedImages[$sniffed];
        } elseif (isset($allowedVideos[$sniffed])) {
            $kind = self::KIND_VIDEO;
            $allowed = $allowedVideos[$sniffed];
        } else {
            $fail(sprintf(
                'The :attribute has an unsupported file type (%s). Allowed: %s.',
                $sniffed,
                implode(', ', array_merge(array_keys($allowedImages), array_keys($allowedVideos)))
            ));
            return;
        }

        if ($this->kind === self::KIND_IMAGE && $kind !== self::KIND_IMAGE) {
            $fail('The :attribute must be an image file.');
            return;
        }
        if ($this->kind === self::KIND_VIDEO && $kind !== self::KIND_VIDEO) {
            $fail('The :attribute must be a video file.');
            return;
        }

        if ($ext !== '' && !in_array($ext, $allowed, true)) {
            $fail(sprintf(
                'The :attribute extension "%s" does not match its detected type (%s). Allowed extensions: %s.',
                $ext,
                $sniffed,
                implode(', ', $allowed)
            ));
            return;
        }

        $imgMax = ($this->maxMbImage ?? (int) config('media.image_max_mb', 8)) * 1024 * 1024;
        $vidMax = ($this->maxMbVideo ?? (int) config('media.video_max_mb', 128)) * 1024 * 1024;
        $sizeMax = $kind === self::KIND_IMAGE ? $imgMax : $vidMax;
        try {
            if ($value->getSize() > $sizeMax) {
                $fail(sprintf(
                    'The :attribute size exceeds the limit of %d MB for %s files.',
                    (int) ceil($sizeMax / 1024 / 1024),
                    $kind
                ));
            }
        } catch (Throwable) {
        }
    }
}
