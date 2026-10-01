<?php

/*
|--------------------------------------------------------------------------
| AI Story Studio (استودیو داستان هوشمند)
|--------------------------------------------------------------------------
|
| API credentials are read from config/services.php (openai / elevenlabs)
| and are only ever used server-side.
|
*/

return [

    'route_prefix' => env('STORY_STUDIO_PREFIX', 'studio'),

    // Wrap with your app's auth stack, e.g. "web,auth".
    'middleware' => array_filter(explode(',', env('STORY_STUDIO_MIDDLEWARE', 'web'))),

    'access' => [
        // Only for local development: lets unauthenticated visitors use the studio.
        'allow_guests' => (bool) env('STORY_STUDIO_ALLOW_GUESTS', false),
        // Comma-separated emails allowed in (in addition to users with is_admin = true).
        'admin_emails' => array_filter(array_map('trim', explode(',', env('STORY_STUDIO_ADMIN_EMAILS', '')))),
        // When true, users only see projects they created.
        'scope_to_owner' => (bool) env('STORY_STUDIO_SCOPE_TO_OWNER', false),
    ],

    'storage' => [
        'disk' => env('STORY_STUDIO_DISK', 'public'),
        'root' => env('STORY_STUDIO_STORAGE_ROOT', 'story-studio'),
    ],

    'queues' => [
        'connection' => env('STORY_STUDIO_QUEUE_CONNECTION'),
        'text' => env('STORY_STUDIO_QUEUE_TEXT', 'story-ai'),
        'image' => env('STORY_STUDIO_QUEUE_IMAGE', 'story-images'),
        'audio' => env('STORY_STUDIO_QUEUE_AUDIO', 'story-audio'),
        'render' => env('STORY_STUDIO_QUEUE_RENDER', 'story-render'),
    ],

    // Which implementation each capability uses. Additional providers can be
    // registered in StoryStudioServiceProvider without touching controllers.
    'providers' => [
        'director' => env('STORY_STUDIO_DIRECTOR_PROVIDER', 'openai'),
        'image' => env('STORY_STUDIO_IMAGE_PROVIDER', 'openai'),
        'speech' => env('STORY_STUDIO_SPEECH_PROVIDER', 'elevenlabs'),
        'voice_design' => env('STORY_STUDIO_VOICE_DESIGN_PROVIDER', 'elevenlabs'),
        'sound_effects' => env('STORY_STUDIO_SFX_PROVIDER', 'elevenlabs'),
    ],

    'openai' => [
        'director_model' => env('STORY_STUDIO_OPENAI_DIRECTOR_MODEL', 'gpt-5'),
        'image_model' => env('STORY_STUDIO_OPENAI_IMAGE_MODEL', 'gpt-image-1'),
        'image_quality' => env('STORY_STUDIO_OPENAI_IMAGE_QUALITY', 'medium'),
        'timeout' => (int) env('STORY_STUDIO_OPENAI_TIMEOUT', 180),
    ],

    'elevenlabs' => [
        'tts_model' => env('STORY_STUDIO_ELEVENLABS_TTS_MODEL', 'eleven_v3'),
        'voice_design_model' => env('STORY_STUDIO_ELEVENLABS_VOICE_DESIGN_MODEL', 'eleven_ttv_v3'),
        'output_format' => env('STORY_STUDIO_ELEVENLABS_OUTPUT_FORMAT', 'mp3_44100_128'),
        'timeout' => (int) env('STORY_STUDIO_ELEVENLABS_TIMEOUT', 120),
        'default_narrator_voice_id' => env('STORY_STUDIO_DEFAULT_NARRATOR_VOICE_ID'),
    ],

    'http' => [
        'retries' => (int) env('STORY_STUDIO_HTTP_RETRIES', 3),
        'retry_base_ms' => (int) env('STORY_STUDIO_HTTP_RETRY_BASE_MS', 1000),
        // Requests per minute per provider (Redis/cache rate limiter).
        'rate_limits' => [
            'openai' => (int) env('STORY_STUDIO_OPENAI_RPM', 60),
            'elevenlabs' => (int) env('STORY_STUDIO_ELEVENLABS_RPM', 60),
        ],
    ],

    'ffmpeg' => [
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
        'timeout' => (int) env('STORY_STUDIO_FFMPEG_TIMEOUT', 1800),
        'subtitle_font' => env('STORY_STUDIO_SUBTITLE_FONT', 'Vazirmatn'),
    ],

    'limits' => [
        'story_max_chars' => (int) env('STORY_STUDIO_STORY_MAX_CHARS', 30000),
        'max_scenes' => (int) env('STORY_STUDIO_MAX_SCENES', 40),
    ],

    /*
    | Price table (USD) used for estimates and actual cost computation.
    | Keep in sync with your provider contracts; values are editable via env.
    */
    'pricing' => [
        'openai_text' => [
            'input_per_million' => (float) env('STORY_STUDIO_PRICE_OPENAI_INPUT', 1.25),
            'output_per_million' => (float) env('STORY_STUDIO_PRICE_OPENAI_OUTPUT', 10.00),
        ],
        'openai_image' => [
            'per_image' => (float) env('STORY_STUDIO_PRICE_OPENAI_IMAGE', 0.063),
        ],
        'elevenlabs' => [
            'tts_per_1k_chars' => (float) env('STORY_STUDIO_PRICE_EL_TTS_1K', 0.30),
            'voice_design_per_call' => (float) env('STORY_STUDIO_PRICE_EL_VOICE_DESIGN', 0.05),
            'sfx_per_generation' => (float) env('STORY_STUDIO_PRICE_EL_SFX', 0.04),
        ],
    ],

];
