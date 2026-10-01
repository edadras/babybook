# AI Story Studio — Technical Plan & Database Schema
استودیو داستان هوشمند

Status: Phase 1 implemented (schema, models, project CRUD, dashboard, wizard, studio shell).
Target: Laravel 11+/13, PHP 8.3, MySQL 8, Redis queues, FFmpeg 6.

---

## 1. Module boundaries

The module is self-contained and namespaced so it never restructures unrelated parts of the host app.
Every folder below is new; the only shared files touched are `bootstrap/providers.php`,
`config/services.php` (two new keys) and `.env.example`.

```
app/
  Enums/StoryStudio/                 ProjectStatus, PipelineStage, AssetType, VisualStyle, ... (Persian labels)
  Models/StoryStudio/                StoryProject, StoryCharacter, StoryScene, StoryAsset, ...
    Concerns/IsVersionedAsset.php    append-only versions + single selected version per slot
  Http/Controllers/StoryStudio/
  Http/Requests/StoryStudio/
  Http/Middleware/EnsureStoryStudioAccess.php
  Policies/StoryStudio/
  Providers/StoryStudioServiceProvider.php   routes, gate, policy, provider bindings
  Services/StoryStudio/
    Contracts/                       (phase 2+) provider interfaces
      StoryDirectorProviderInterface, ImageGenerationProviderInterface,
      SpeechProviderInterface, VoiceDesignProviderInterface, SoundEffectProviderInterface
    Providers/OpenAI/*, Providers/ElevenLabs/*, Providers/Fake/*
    StoryDirectorService, CharacterReferenceService, ImageDirectorService,
    VisualContinuityService, PronunciationDictionaryService, VoiceDirectorService,
    StoryPipelineService, DependencyInvalidator, GenerationGuard, CostTracker,
    AudioMixService, VideoRenderService, SubtitleService, CoverService,
    SafetyAnalysisService, ProjectProgressService
  Jobs/StoryStudio/                  (phase 2+) one job per expensive step
  Support/StoryStudio/Fa.php         Persian digits, Jalali dates, durations
config/story-studio.php              providers, models, prices, disks, queues, access, limits
routes/story-studio.php              mounted at /studio (configurable prefix + middleware)
resources/views/story-studio/        Blade, RTL, Persian
public/vendor/story-studio/          studio.css / studio.js (no build step)
database/migrations/2026_10_01_1000xx_*story*
database/factories/StoryStudio/
tests/Feature/StoryStudio, tests/Unit/StoryStudio
```

### Access control
Story Studio triggers paid API calls, so it is closed by default:
- `STORY_STUDIO_ADMIN_EMAILS=a@x.com,b@y.com`. Users with these emails (or `is_admin = true`) may enter.
- `STORY_STUDIO_MIDDLEWARE=web,auth` lets the host app's auth stack wrap the routes.
- `STORY_STUDIO_ALLOW_GUESTS=true` is **local development only**.
- `STORY_STUDIO_SCOPE_TO_OWNER=true` limits each user to the projects they created.

## 2. Data model

Conventions: `id` = ULID/UUID primary key (char(26)/uuid) on all story tables, so IDs are
non-enumerable and can be generated before insert (needed for idempotency keys). JSON columns
for AI structured data, but anything the app queries or edits gets a real column.
Soft deletes on projects, scenes, and characters.

### story_projects
| column | type | notes |
|---|---|---|
| id | uuid pk | |
| user_id | fk nullable | owner (host users table) |
| title | string | |
| original_text | longText | **never modified** |
| language | string(8) | fa, en |
| target_age | string(8) | 3-5, 6-8, 9-12 |
| category | string | |
| visual_style | string | enum + `custom` |
| custom_visual_instructions | text null | |
| narration_style | string | |
| aspect_ratio | string(5) | 16:9, 9:16, 1:1 |
| resolution | string(6) | 720p, 1080p |
| mode | string | auto / manual |
| status | string | ProjectStatus enum (Draft…Failed) |
| current_stage | string null | PipelineStage |
| progress | tinyint | 0–100, derived |
| paused_at, cancelled_at | timestamp null | |
| story_metadata | json null | director "story" block |
| style_bible | json null | global visual style bible |
| safety_report | json null | warnings + acknowledged_by/at |
| cover_asset_id | uuid null | |
| duration_ms | int null | computed from timeline |
| timestamps, softDeletes | | |

### story_project_settings  (1:1)
music defaults, ducking dB, default transition + duration, default motion, subtitle mode
(none/fa/en/bilingual), burn_subtitles, subtitle font size, provider overrides per stage,
budget cap.

### story_characters
id, project_id, `key` (director character_id, e.g. `rabbit`), name, role, is_narrator,
is_major, age_group, gender null, personality, visual_description, clothing, hair, face,
colors (json), height_relation, special_features, voice_description, voice_profile_id null,
reference_asset_id null, **character_locked** bool, locked_at, sort_order.
unique(project_id, key)

### story_voice_profiles
id, project_id, character_id null, provider, provider_voice_id (elevenlabs_voice_id),
name, description, source (library/designed/cloned), settings json (stability, style…),
status (candidate/selected/rejected), **locked** bool, preview_asset_id null.
Candidates are rows with status=candidate; selection flips one to selected and sets
character.voice_profile_id. Scenes always read the voice via the character → never regenerate.

### story_locations
id, project_id, key, name, description, visual_description, time_of_day_default,
reference_asset_id null. unique(project_id, key)

### story_scenes
id, project_id, scene_number, sort_order, title, location_id null, time_of_day,
duration_estimate_ms, duration_ms (editor override),
narration_original, narration_tts (pronunciation-normalised),
visual json (description, shot_type, camera_angle, lighting, character_actions),
director_prompt text, final_image_prompt text, prompt_edited bool,
audio_plan json (ambience, music_mood),
motion (none/zoom_in/zoom_out/pan_left/pan_right/ken_burns/auto), transition,
transition_duration_ms, music_track_asset_id null, music_volume, music_fade_in_ms,
music_fade_out_ms, status, stale_flags json, approved_at, timestamps, softDeletes.
Pivot **story_scene_characters** (scene_id, character_id, action, position).

### story_dialogues
id, scene_id, character_id, sort_order, text_original, text_tts, emotion, energy, pace,
delivery, provider_tags (e.g. ElevenLabs `[worried]`), start_offset_ms, volume, status.

### story_sound_effects  (added; needed for per-effect enable/disable)
id, scene_id, kind (sfx/ambience), description, prompt, enabled, start_offset_ms,
duration_ms, volume, loop, sort_order.

### story_assets  (all binary outputs, visual + misc)
id, project_id, scene_id null, character_id null, location_id null, generation_job_id null,
type (scene_image, character_ref_front, character_ref_34, character_ref_full, cover,
cover_art, scene_video, final_video, final_audio, srt, vtt, zip),
provider, model, prompt text, disk, file_path, mime, width, height, duration_ms,
version (int), **selected** bool, status (pending/generating/ready/failed/stale),
metadata json, generation_time_ms, estimated_cost, actual_cost, generation_id fk,
created_at. `slot` is a deterministic group key (e.g. `scene:{uuid}:scene_image`);
unique(project_id, slot, version). Never updated in place; a new version is a new row.

### story_audio_assets
Same versioning shape as story_assets, specialised for audio: id, project_id, scene_id,
dialogue_id null, character_id null, sound_effect_id null, voice_profile_id null,
type (narration, dialogue, sfx, ambience, music, scene_mix, voice_preview),
provider, model, voice_id, text/prompt, tts_text, file_path, duration_ms, version, selected,
status, metadata, cost fields, generation_id.

### story_generation_jobs  (idempotency + cost ledger)
id, project_id, **idempotency_key** (unique), stage, job_class, target_type/target_id,
provider, model, status (queued/running/succeeded/failed/cancelled), attempts,
request_hash, request_summary json (redacted), response_summary json (redacted),
error_code, error_message, input_units, output_units, unit_type (tokens/chars/images/seconds),
estimated_cost, actual_cost, cost_category (openai_text/openai_image/el_voice_design/
el_narration/el_dialogue/el_sfx), started_at, finished_at.

### story_render_jobs
id, project_id, scope (scene/preview/final), scene_id null, status, progress,
ffmpeg_command (redacted log), output asset ids json, log_path, error, started/finished.

### story_pronunciations
id, project_id null (null = global dictionary), word, pronunciation, type
(name/place/general/phoneme), alphabet (text/ipa/cmu), case_sensitive, notes.
unique(project_id, word).

### story_continuity_states
id, project_id, scene_id (state *after* this scene), state json
(characters → outfit/props, weather, time_of_day, location, introduced objects),
source (director/manual). Lets scene N read the state after scene N-1.

### story_pipeline_runs (added; orchestrator bookkeeping)
id, project_id, mode, status, current_stage, stage_states json, batch_id (Bus batch),
started/finished, paused/cancelled.

## 3. Key mechanisms

**Structured AI output.** OpenAI Responses API with `text.format = json_schema, strict: true`;
the same schema is re-validated server-side (opis/json-schema) and then mapped through DTOs.
Invalid output is a failed generation, never partially stored. The original text is never rewritten.

**Character lock.** `CharacterReferenceService::blockFor(scene)` builds an immutable descriptor
from locked character columns (not from the director's scene text) and is injected verbatim.
When the image provider supports references (gpt-image edits with input images), the selected
reference image is attached as well.

**Image prompt composition.** GLOBAL_STYLE + CHARACTER_REFERENCES + LOCATION + SCENE + COMPOSITION
+ CAMERA + LIGHTING + CONTINUITY, built deterministically from DB rows by `ImageDirectorService`.
`director_prompt` and `final_image_prompt` are both stored; an admin edit sets `prompt_edited`
and is respected on regeneration.

**Idempotency.** Each job computes `idempotency_key = hash(stage, target_id, input_hash, version_intent)`.
`GenerationGuard` takes a DB row lock: a `succeeded` row means skip; `running` and not timed out
means release; otherwise claim and call the provider. The asset row is created by the same
transaction that marks success. ElevenLabs/OpenAI request IDs are stored for audit.

**Dependency invalidation.** A static graph:
```
dialogue.text  → dialogue_audio → scene_mix → scene_video → final_render
narration      → narration_audio → scene_mix → ...
scene.visual / prompt → scene_image → scene_video → final_render
character.visual (only when unlocked) → char refs → images of scenes containing the character
voice change   → all audio of that character → their scene mixes → ...
sfx toggle     → scene_mix → ...
timeline edit  → scene_video → final_render
```
Implemented with model observers that call `DependencyInvalidator::touched($model, $fields)`,
which sets `status = stale` on downstream rows only.

**Pipeline.** `StoryPipelineService` evaluates which stages are incomplete or stale and dispatches
`Bus::batch` per stage, chaining the next stage on completion. In manual mode it stops at each
approval gate. Pause sets a flag that jobs check before calling providers; cancel cancels the
batch; retry re-dispatches only failed or stale items. Completed assets are never deleted.

**Progress.** Derived from DB counts per stage, served by a JSON polling endpoint every 2–3 s
(no WebSocket infra required). Broadcasting can be added later.

**Audio.** Per-scene FFmpeg `filter_complex`: narration/dialogue placed with `adelay`, SFX/ambience
mixed, music `sidechaincompress`-ducked under the voice bus, `loudnorm` to −16 LUFS.

**Video.** Per-scene clip: `zoompan` for subtle motion (≤ 1.08 zoom), scene mix muxed in. Final:
`xfade`/`acrossfade` chain (default crossfade of 0.6 s), optional subtitle burn-in via `subtitles=`
with a Vazirmatn font (libass handles RTL shaping), plus MP3, SRT, VTT, cover.jpg, and ZIP.

**Cover.** Text-free artwork from the image model; the Persian title is overlaid with Imagick or
GD plus a shaping step (or FFmpeg `drawtext` with libfribidi/harfbuzz) so the result is deterministic.

**Security.** Keys live only in `config/services.php` via env. HTTP clients have timeouts,
`retry()` with backoff on 429/5xx, and a Redis rate limiter per provider. Logged
requests and responses pass through a redactor.

**Safety.** `SafetyAnalysisService` runs first and stores categories with severity and excerpts.
The UI shows warnings and requires acknowledgement before generation. Text is never auto-rewritten.

**Costs.** A price table in config (per 1M tokens, per image size/quality, per 1k characters,
per SFX second). Estimates come from story length × scene count before "Generate Full Story";
actuals come from provider usage fields, aggregated per cost_category on the cost dashboard.

## 4. UI

Blade + small vanilla JS (no mandatory npm build), RTL, Vazirmatn font. A studio shell with the
project nav on the left (as specified), canvas in the centre, inspector on the right, and the
timeline on the bottom. Tabs: داستان · شخصیت‌ها · صداها · استوری‌بورد · صداگذاری · تایم‌لاین ·
رندر · خروجی. Drag-and-drop ordering uses native HTML5 DnD with a reorder endpoint.

## 5. Phases (each ends with migrate + tests + commit)

1. Migrations, models, enums, factories, project CRUD, dashboard and wizard UI, config/provider skeleton.
2. Director provider + schema validation + AnalyzeStoryJob + safety report.
3. Character bible, locations, storyboard UI, continuity state.
4. ElevenLabs: voice library/design candidates, casting UI, narration/dialogue TTS, pronunciation.
5. Image director + OpenAI images + versioning + character references.
6. SFX + scene audio mix with ducking.
7. FFmpeg scene renderer (motion).
8. Final renderer, subtitles, cover, ZIP, final page.
9. Timeline editor.
10. Cost dashboard, pipeline pause/resume/cancel polish, invalidation hardening.

Tests use `Http::fake()` and the Fake providers, so no real API calls are made in CI.

## 6. Running locally

```bash
composer install
cp .env.example .env && php artisan key:generate
# MySQL: set DB_CONNECTION=mysql, DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan migrate
php artisan storage:link
# Either allow your admin account in…
echo "STORY_STUDIO_ADMIN_EMAILS=you@example.com" >> .env
# …or, for local experiments only:
echo "STORY_STUDIO_ALLOW_GUESTS=true" >> .env
php artisan serve        # → http://127.0.0.1:8000/studio
php artisan queue:work --queue=story-ai,story-images,story-audio,story-render   # phase 2+
php artisan test
```
