<?php

return [
    'image_max_mb' => (int) env('MEDIA_IMAGE_MAX_MB', 8),
    'video_max_mb' => (int) env('MEDIA_VIDEO_MAX_MB', 128),
    'signed_view_ttl_minutes' => (int) env('MEDIA_SIGNED_TTL_MINUTES', 30),
];
