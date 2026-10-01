<?php

namespace App\Services\StoryStudio;

use App\Enums\StoryStudio\AssetStatus;
use App\Enums\StoryStudio\CostCategory;
use App\Enums\StoryStudio\ImageMotion;
use App\Enums\StoryStudio\PipelineStage;
use App\Enums\StoryStudio\SoundKind;
use App\Models\StoryStudio\StoryCharacter;
use App\Models\StoryStudio\StoryProject;
use App\Services\StoryStudio\Contracts\StoryDirectorProviderInterface;
use App\Services\StoryStudio\Exceptions\InvalidAiOutputException;
use App\Services\StoryStudio\Schema\StorySchemas;
use App\Services\StoryStudio\Support\JsonSchemaValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The AI Director: analyses and directs the author's story into a structured,
 * validated production script. It never rewrites the original text.
 */
class StoryDirectorService
{
    public function __construct(
        private readonly StoryDirectorProviderInterface $provider,
        private readonly JsonSchemaValidator $validator,
        private readonly GenerationGuard $guard,
        private readonly PronunciationDictionaryService $pronunciation,
    ) {}

    /**
     * Run (or reuse) the analysis for the given idempotency nonce and persist it.
     */
    public function analyzeAndStore(StoryProject $project, string $nonce): void
    {
        $key = GenerationGuard::key('analyze', $project->getKey(), md5($project->original_text), $nonce);

        $run = $this->guard->run(
            $project, $key, PipelineStage::Analyze, 'story.analyze', $project,
            $this->provider->name(), $this->provider->model(), CostCategory::OpenAIText,
            ['characters' => mb_strlen($project->original_text), 'schema' => 'story_analysis'],
            function () use ($project) {
                $result = $this->provider->generateStructured(
                    $this->systemPrompt(),
                    $this->userPrompt($project),
                    StorySchemas::analysis(),
                    'story_analysis',
                );

                $errors = $this->validator->validate($result->data, StorySchemas::analysis());
                if ($errors !== []) {
                    throw new InvalidAiOutputException($errors);
                }

                $this->persist($project, $result->data, $result->model);

                return new GenerationOutcome(
                    null,
                    $result->inputTokens,
                    $result->outputTokens,
                    'tokens',
                    $result->requestId,
                    ['scenes' => count($result->data['scenes']), 'characters' => count($result->data['characters'])],
                );
            },
        );

        // A duplicate execution of an already-succeeded analysis changes nothing.
        unset($run);
    }

    /**
     * Regenerate the Character Bible entry of one (unlocked) character.
     */
    public function refineCharacter(StoryCharacter $character, string $nonce): void
    {
        abort_if($character->character_locked, 409, 'شخصیت قفل است.');
        $project = $character->project;
        $key = GenerationGuard::key('character-bible', $character->getKey(), $nonce);

        $this->guard->run(
            $project, $key, PipelineStage::Characters, 'character.bible', $character,
            $this->provider->name(), $this->provider->model(), CostCategory::OpenAIText,
            ['character' => $character->key],
            function () use ($character, $project) {
                $user = "Story context (do not rewrite):\n<story>".$project->original_text."</story>\n"
                    ."Character id: {$character->key}\n<name>{$character->name}</name>\nRole: {$character->role}\n"
                    .'Current bible: '.json_encode($character->only(StoryCharacter::VISUAL_IDENTITY_FIELDS), JSON_UNESCAPED_UNICODE)."\n"
                    .'Write a more precise, production-ready Character Bible entry in English. Keep anything the story states. '
                    .'Visual identity must be specific enough that an illustrator draws the same character in every scene.';

                $result = $this->provider->generateStructured($this->systemPrompt(), $user, StorySchemas::characterBible(), 'character_bible');
                $errors = $this->validator->validate($result->data, StorySchemas::characterBible());
                if ($errors !== []) {
                    throw new InvalidAiOutputException($errors);
                }

                $character->fill($result->data)->save();

                return new GenerationOutcome(null, $result->inputTokens, $result->outputTokens, 'tokens', $result->requestId);
            },
        );
    }

    public function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the AI Director of a professional studio that turns children's stories into illustrated audiobooks and gently animated storybook videos.
Think like a children's animation director, not a text converter. For every scene, decide internally:
who is present, what they are doing, what the viewer should see, who speaks and how, which emotion is present,
what should be heard in the environment, which camera composition communicates the moment, how it connects visually
to the previous scene, and how long the moment should last. The result must feel intentionally directed.

ABSOLUTE RULES
1. Never rewrite, summarise, censor or embellish the author's story. Split the original text into scenes, in order.
   - `narration` must contain the author's exact sentences, verbatim and in the original language.
   - Lines spoken by characters go to `dialogues` with their exact original wording (without quotation marks),
     and are removed from the narration. Narration + dialogues must together cover the whole story.
2. Characters: give each a stable snake_case `id`. Do NOT list the narrator; the studio adds it automatically.
   The Character Bible (visual_description, clothing, hair, face, colors, height_relation, special_features) is written in English,
   is very specific, and is the LOCKED visual identity used in every image. Never vary a character between scenes.
   Mark characters who appear in several scenes or speak as is_major=true.
3. Locations: stable snake_case `id`, English visual description. Scenes reference them via location_id.
4. All visual and audio fields are English. `image_prompt` describes only this scene's action, staging, environment and composition.
   Refer to characters by name and id; do NOT redescribe their appearance (the studio injects the locked Character Bible).
   Images must be gentle and age-appropriate: no gore, no menace, soft expressions even in tense moments.
5. Sound design supports the story without distracting from narration: 0–2 sound effects per scene, only when meaningful.
   Ambience is optional (null when unnecessary). Pick a music_mood per scene.
6. duration_estimate (seconds) = reading time of narration and dialogues at a calm children's pace (about 12 Persian characters per second)
   plus about 2 seconds of breathing room.
7. Voice direction for each dialogue: emotion, energy, pace and a short delivery note (e.g. "calling from a distance", "whispering").
8. Continuity: list important props, outfit changes and weather so later scenes stay consistent
   (e.g. a red backpack introduced in scene 3 stays red).
9. recommended_motion must be subtle and suit an illustrated storybook.
10. Provide faithful English translations in narration_en / text_en for bilingual subtitles (null only if the story is already English).
11. Safety analysis for the given target age: flag (never rewrite) violence, sexual content, extreme fear, self-harm,
    dangerous imitation, hate or adult themes, quoting a short excerpt. Use overall="safe" when nothing is found.
PROMPT;
    }

    public function userPrompt(StoryProject $project): string
    {
        $style = $project->visual_style->promptFragment() ?? 'Custom style (see instructions)';

        return implode("\n", [
            'Project: '.$project->title,
            'Language: '.$project->language,
            'Target age: '.$project->target_age->value,
            'Category: '.$project->category->value,
            'Visual style: '.$style,
            'Custom visual instructions: '.($project->custom_visual_instructions ?: 'none'),
            'Narration style: '.$project->narration_style->value,
            'Video format: '.$project->aspect_ratio->value,
            '<max_scenes>'.config('story-studio.limits.max_scenes').'</max_scenes>',
            'Original story (verbatim, do not modify):',
            '<story>'.$project->original_text.'</story>',
        ]);
    }

    /**
     * Store a validated analysis. Locked characters keep their approved bible;
     * previous scenes are soft-deleted (their assets are kept).
     *
     * @param  array<string, mixed>  $data
     */
    public function persist(StoryProject $project, array $data, string $model = 'unknown'): void
    {
        DB::transaction(function () use ($project, $data, $model) {
            $settings = $project->settings()->firstOrCreate();
            $warnings = $this->coverageWarnings($project->original_text, $data['scenes']);

            $safety = $data['safety'];
            $safety['requires_acknowledgement'] = $safety['overall'] !== 'safe'
                && collect($safety['flags'])->contains(fn ($flag) => in_array($flag['severity'], ['medium', 'high'], true));

            $project->forceFill([
                'story_metadata' => array_merge($data['story'], ['director_model' => $model, 'warnings' => $warnings, 'analyzed_at' => now()->toIso8601String()]),
                'style_bible' => [
                    'base' => $project->visual_style->promptFragment(),
                    'custom' => $project->custom_visual_instructions,
                    'global' => $data['story']['visual_style_bible'],
                    'palette' => $data['story']['color_palette'],
                ],
                'safety_report' => $safety,
                'safety_acknowledged_at' => null,
                'script_approved_at' => null,
                'storyboard_approved_at' => null,
                'review_approved_at' => null,
                'duration_ms' => (int) ($data['story']['estimated_duration_seconds'] * 1000),
            ])->save();

            // Characters (upsert by key; locked bibles are never touched).
            $characters = [];
            $keepKeys = ['narrator'];
            foreach (array_values($data['characters']) as $index => $row) {
                $key = Str::snake(Str::limit($row['id'], 60, ''));
                $keepKeys[] = $key;
                $character = StoryCharacter::withTrashed()->firstOrNew(['project_id' => $project->getKey(), 'key' => $key]);
                $character->project_id = $project->getKey();
                if ($character->exists && $character->trashed()) {
                    $character->restore();
                }

                $base = ['name' => $row['name'], 'role' => $row['role'], 'is_major' => $row['is_major'], 'age_group' => $row['age_group'], 'gender' => $row['gender'], 'sort_order' => $index + 1];
                $bible = array_intersect_key($row, array_flip([...StoryCharacter::VISUAL_IDENTITY_FIELDS, 'personality', 'voice_description']));
                $character->fill($character->character_locked ? $base : array_merge($base, $bible));
                $character->save();
                $characters[$row['id']] = $character;
            }

            $narrator = StoryCharacter::withTrashed()->firstOrNew(['project_id' => $project->getKey(), 'key' => 'narrator']);
            $narrator->project_id = $project->getKey();
            if ($narrator->exists && $narrator->trashed()) {
                $narrator->restore();
            }
            $narrator->fill([
                'name' => 'راوی',
                'role' => 'narrator',
                'is_narrator' => true,
                'is_major' => false,
                'sort_order' => 0,
                'voice_description' => $narrator->voice_description ?: $this->narratorVoiceDescription($project),
            ])->save();

            $project->characters()->whereNotIn('key', $keepKeys)->where('character_locked', false)->get()->each->delete();

            // Locations.
            $locations = [];
            foreach (array_values($data['locations']) as $index => $row) {
                $location = $project->locations()->firstOrNew(['key' => Str::snake(Str::limit($row['id'], 60, ''))]);
                $location->fill([
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'visual_description' => $row['visual_description'],
                    'default_time_of_day' => $row['default_time_of_day'],
                    'sort_order' => $index + 1,
                ])->save();
                $locations[$row['id']] = $location;
            }

            // Scenes replace the previous script.
            $project->scenes()->get()->each->delete();
            $project->continuityStates()->delete();

            foreach (array_values($data['scenes']) as $index => $row) {
                $durationMs = (int) round(max(3, $row['duration_estimate']) * 1000);
                $scene = $project->scenes()->create([
                    'scene_number' => $index + 1,
                    'sort_order' => $index + 1,
                    'title' => $row['title'],
                    'location_id' => isset($locations[$row['location_id'] ?? '']) ? $locations[$row['location_id']]->getKey() : null,
                    'time_of_day' => $row['time_of_day'],
                    'duration_estimate_ms' => $durationMs,
                    'narration_original' => $row['narration'],
                    'narration_tts' => $this->pronunciation->normalize($row['narration'], $project),
                    'narration_en' => $row['narration_en'],
                    'visual' => $row['visual'],
                    'director_prompt' => $row['visual']['image_prompt'],
                    'audio_plan' => ['ambience' => $row['audio']['ambience'], 'music_mood' => $row['audio']['music_mood']],
                    'music_mood' => $row['audio']['music_mood'],
                    'motion' => ImageMotion::Auto,
                    'transition' => $settings->default_transition,
                ]);
                $scene->forceFill(['work_state' => [], 'status' => AssetStatus::Pending])->save();

                foreach ($row['characters_present'] as $presence) {
                    if (isset($characters[$presence['character_id']])) {
                        $scene->characters()->syncWithoutDetaching([
                            $characters[$presence['character_id']]->getKey() => ['action' => $presence['action'], 'position' => $presence['position']],
                        ]);
                    }
                }

                foreach (array_values($row['dialogues']) as $order => $line) {
                    $speaker = $characters[$line['character_id']] ?? null;
                    if ($speaker) {
                        $scene->characters()->syncWithoutDetaching([$speaker->getKey()]);
                    }
                    $scene->dialogues()->create([
                        'character_id' => $speaker?->getKey(),
                        'sort_order' => $order + 1,
                        'text_original' => $line['text'],
                        'text_tts' => $this->pronunciation->normalize($line['text'], $project),
                        'text_en' => $line['text_en'],
                        'emotion' => $line['emotion'],
                        'energy' => $line['energy'],
                        'pace' => $line['pace'],
                        'delivery' => $line['delivery'],
                    ]);
                }

                $order = 0;
                if ($row['audio']['ambience']) {
                    $scene->soundEffects()->create([
                        'kind' => SoundKind::Ambience,
                        'description' => $row['audio']['ambience'],
                        'prompt' => $row['audio']['ambience'].', seamless background ambience, no music, no voices',
                        'loop' => true,
                        'volume' => 0.25,
                        'duration_ms' => 10000,
                        'sort_order' => $order++,
                    ]);
                }
                foreach (array_slice($row['audio']['sound_effects'], 0, 3) as $effect) {
                    $scene->soundEffects()->create([
                        'kind' => SoundKind::Effect,
                        'description' => $effect['description'],
                        'prompt' => $effect['prompt'],
                        'start_offset_ms' => match ($effect['timing']) {
                            'start' => 200,
                            'with_narration' => 600,
                            'middle' => (int) ($durationMs / 2),
                            'end' => max(0, $durationMs - 2500),
                        },
                        'volume' => 0.6,
                        'sort_order' => $order++,
                    ]);
                }

                $outfits = [];
                foreach ($row['continuity']['outfit_changes'] as $change) {
                    $outfits[$change['character_id']] = $change['change'];
                }
                $project->continuityStates()->create([
                    'scene_id' => $scene->getKey(),
                    'state' => [
                        'props' => $row['continuity']['props'],
                        'outfits' => $outfits,
                        'weather' => $row['continuity']['weather'],
                        'time_of_day' => $row['time_of_day'],
                        'location' => $row['location_id'],
                        'notes' => $row['continuity']['notes'],
                    ],
                ]);
            }

            $project->forceFill(['work_state' => []])->save();
        });
    }

    private function narratorVoiceDescription(StoryProject $project): string
    {
        return match ($project->narration_style->value) {
            'bedtime' => "Warm Persian storyteller, gentle, calm and soothing, perfect for children's bedtime stories.",
            'playful' => 'Bright, smiling Persian storyteller with playful energy for young children.',
            'dramatic' => 'Expressive Persian storyteller with rich dynamics, warm and never scary.',
            'educational' => 'Clear, friendly Persian teacher-like narrator, measured pace.',
            default => "Warm Persian storyteller, gentle, calm, expressive, appropriate for children's stories.",
        };
    }

    /**
     * Detect narration that drifted from the original text (the director must not paraphrase).
     *
     * @param  list<array<string, mixed>>  $scenes
     * @return list<string>
     */
    private function coverageWarnings(string $original, array $scenes): array
    {
        $normalize = fn (string $text) => preg_replace('/[\s«»"“”:،,.!؟?\-—]+/u', '', $text);
        $source = $normalize($original);
        $warnings = [];

        foreach ($scenes as $index => $scene) {
            foreach (preg_split('/(?<=[.!؟?])\s+/u', (string) $scene['narration']) ?: [] as $sentence) {
                $clean = $normalize($sentence);
                if (mb_strlen($clean) > 12 && ! str_contains($source, $clean)) {
                    $warnings[] = 'صحنه '.($index + 1).': روایت با متن اصلی منطبق نیست: «'.Str::limit($sentence, 60).'»';
                }
            }
        }

        return array_slice($warnings, 0, 20);
    }
}
