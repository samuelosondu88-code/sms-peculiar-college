<?php
namespace App\Services;

use App\Contracts\AiProviderInterface;
use App\Services\AiProviders\AnthropicProvider;
use App\Services\AiProviders\GeminiProvider;
use App\Services\AiProviders\OpenAiProvider;
use App\Services\AiProviders\TemplateProvider;

/**
 * The AI Teaching Assistant service.
 *
 * Provider-agnostic: picks a concrete provider from configuration (see
 * resolve()) so OpenAI, Anthropic, Gemini or the built-in template can be
 * swapped in via .env without changing application code.
 */
class AiAssistantService
{
    /** Action key => ['label' => ..., 'sections' => [...]] */
    public const ACTIONS = [
        'lesson_note' => [
            'label' => 'Lesson Note',
            'sections' => ['title', 'learning_objectives', 'introduction', 'body', 'summary', 'assignment', 'reference_materials'],
        ],
        'lesson_plan' => [
            'label' => 'Lesson Plan',
            'sections' => ['sub_topic', 'learning_objectives', 'previous_knowledge', 'instructional_materials', 'teaching_methods', 'introduction', 'presentation_steps', 'classroom_activities', 'student_activities', 'assessment', 'assignment', 'reference_materials', 'remarks'],
        ],
        'objectives' => ['label' => 'Lesson Objectives', 'sections' => ['objectives']],
        'instructional_materials' => ['label' => 'Instructional Materials', 'sections' => ['instructional_materials']],
        'class_activities' => ['label' => 'Class Activities', 'sections' => ['classroom_activities', 'student_activities']],
        'assignments' => ['label' => 'Assignments', 'sections' => ['assignment']],
        'quiz_questions' => ['label' => 'Quiz Questions', 'sections' => ['quiz_questions']],
        'exam_questions' => ['label' => 'Examination Questions', 'sections' => ['exam_questions']],
        'marking_guide' => ['label' => 'Marking Guide', 'sections' => ['marking_guide']],
        'teaching_methods' => ['label' => 'Teaching Methods', 'sections' => ['teaching_methods']],
        'summary' => ['label' => 'Lesson Summary', 'sections' => ['summary']],
        'differentiation' => ['label' => 'Differentiation', 'sections' => ['differentiation']],
        'blooms_objectives' => ['label' => "Bloom's Taxonomy Objectives", 'sections' => ['blooms_objectives']],
        'curriculum_alignment' => ['label' => 'Curriculum Alignment (NERDC/WAEC/NECO/BECE)', 'sections' => ['curriculum_alignment']],
    ];

    public function __construct(private ?AiProviderInterface $provider = null)
    {
        $this->provider = $provider ?? self::resolve();
    }

    /**
     * Resolve a provider from configuration. Falls back to the offline
     * template provider when no provider/key is configured.
     */
    public static function resolve(): AiProviderInterface
    {
        $provider = strtolower(trim((string)(env('AI_PROVIDER') ?: '')));
        $timeout  = (int)(env('AI_TIMEOUT') ?: 60);

        return match ($provider) {
            'openai'    => (env('OPENAI_API_KEY') ? new OpenAiProvider(env('OPENAI_API_KEY'), env('OPENAI_MODEL') ?: 'gpt-4o-mini', $timeout) : new TemplateProvider()),
            'anthropic' => (env('ANTHROPIC_API_KEY') ? new AnthropicProvider(env('ANTHROPIC_API_KEY'), env('ANTHROPIC_MODEL') ?: 'claude-3-5-sonnet-20241022', $timeout) : new TemplateProvider()),
            'gemini'    => (env('GEMINI_API_KEY') ? new GeminiProvider(env('GEMINI_API_KEY'), env('GEMINI_MODEL') ?: 'gemini-1.5-pro', $timeout) : new TemplateProvider()),
            default     => new TemplateProvider(),
        };
    }

    public function provider(): AiProviderInterface
    {
        return $this->provider;
    }

    public function isTemplate(): bool
    {
        return $this->provider instanceof TemplateProvider;
    }

    /**
     * Generate content for an action.
     *
     * @param string $action  key of self::ACTIONS
     * @param array<string,mixed> $ctx
     * @param string|null $section  regenerate only this section, or null for all
     * @return array<string,string>  map of section key => content
     */
    public function generate(string $action, array $ctx, ?string $section = null): array
    {
        if (!isset(self::ACTIONS[$action])) {
            throw new \InvalidArgumentException("Unknown AI action: $action");
        }

        $sections = self::ACTIONS[$action]['sections'];
        if ($section !== null) {
            if (!in_array($section, $sections, true)) {
                throw new \InvalidArgumentException("Section '$section' not part of action '$action'");
            }
            $sections = [$section];
        }

        try {
            if ($this->provider instanceof TemplateProvider) {
                $result = $this->provider->generateStructured($action, $ctx, $sections);
            } else {
                $result = $this->generateViaApi($action, $ctx, $sections, $section);
            }
            $this->log($action, $ctx, 'success');
            return $result;
        } catch (\Throwable $e) {
            $this->log($action, $ctx, 'error', $e->getMessage());
            throw $e;
        }
    }

    private function generateViaApi(string $action, array $ctx, array $sections, ?string $section): array
    {
        $prompt = $this->buildPrompt($action, $ctx, $sections, $section);
        $raw = $this->provider->chat([
            ['role' => 'system', 'content' => $this->systemPrompt($action, $sections)],
            ['role' => 'user', 'content' => $prompt],
        ], ['temperature' => 0.7, 'max_tokens' => 4000]);

        return $this->parseJson($raw, $sections, $ctx);
    }

    private function systemPrompt(string $action, array $sections): string
    {
        return "You are an expert Nigerian secondary school teacher and curriculum specialist. "
            . "You create high-quality, ready-to-use teaching content aligned with the Nigerian curriculum "
            . "(NERDC basic education curriculum, and WAEC/NECO/BECE examination standards where applicable). "
            . "Respond with a SINGLE valid JSON object containing EXACTLY these keys: "
            . implode(', ', array_map(fn($s) => '"' . $s . '"', $sections)) . ". "
            . "Use plain text with simple line breaks inside each value (no markdown headers, no HTML). "
            . "Do not wrap the JSON in code fences and do not add anything outside the JSON object.";
    }

    private function buildPrompt(string $action, array $ctx, array $sections, ?string $section): string
    {
        $lines = [];
        $lines[] = 'Action: ' . self::ACTIONS[$action]['label'];
        $lines[] = 'Subject: ' . ($ctx['subject_name'] ?? 'General');
        $lines[] = 'Class: ' . ($ctx['class_name'] ?? '');
        $lines[] = 'Level: ' . ($ctx['level'] ?? '');
        $lines[] = 'Topic: ' . ($ctx['topic'] ?? '');
        $lines[] = 'Week of term: ' . ($ctx['week'] ?? '');
        if (!empty($ctx['term_name'])) $lines[] = 'Term: ' . $ctx['term_name'];
        if (!empty($ctx['session_name'])) $lines[] = 'Session: ' . $ctx['session_name'];
        if (!empty($ctx['extra'])) $lines[] = 'Additional context: ' . $ctx['extra'];
        $lines[] = 'Generate content for: ' . implode(', ', $sections);

        if ($action === 'lesson_plan' || $action === 'lesson_note') {
            $lines[] = 'Structure the lesson for a typical 40-minute period and include age-appropriate activities.';
        }
        if (in_array($action, ['quiz_questions', 'exam_questions'], true)) {
            $lines[] = 'Format "quiz_questions" / "exam_questions" as a JSON ARRAY (as a string value) of items with question, options (array of A-D), answer, marks and explanation where applicable.';
        }
        if ($section !== null) {
            $lines[] = 'ONLY generate the "' . $section . '" section.';
        }
        return implode("\n", $lines);
    }

    private function parseJson(string $raw, array $sections, array $ctx): array
    {
        $trimmed = trim($raw);
        // Strip code fences if a model wrapped the JSON.
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $trimmed, $m)) {
            $trimmed = trim($m[1]);
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            // Fall back: keep the raw text as the primary section.
            $primary = $sections[0];
            return [$primary => $raw];
        }

        $out = [];
        foreach ($sections as $section) {
            $value = $decoded[$section] ?? '';
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            $out[$section] = trim((string)$value);
            if ($out[$section] === '' && $this->provider instanceof TemplateProvider === false) {
                $out[$section] = 'No content returned for this section.';
            }
        }
        return $out;
    }

    private function log(string $action, array $ctx, string $status, string $error = ''): void
    {
        try {
            $db = getDB();
            $stmt = $db->prepare(
                "INSERT INTO ai_generation_log (teacher_id, action, provider, model, prompt, status, error_message)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                (int)($_SESSION['user_id'] ?? 0),
                $action,
                $this->provider->name(),
                $this->provider->name() === 'template' ? null : $this->provider->label(),
                mb_substr($ctx['extra'] ?? '', 0, 2000) ?: mb_substr(implode(' ', $ctx), 0, 2000),
                $status,
                $error !== '' ? mb_substr($error, 0, 1000) : null,
            ]);
        } catch (\Throwable $e) {
            // Logging must never break generation.
            error_log('AI generation log failed: ' . $e->getMessage());
        }
    }
}
