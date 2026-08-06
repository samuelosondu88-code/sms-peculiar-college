<?php
namespace App\Services\AiProviders;

use App\Contracts\AiProviderInterface;

/**
 * Deterministic, offline "template" provider.
 *
 * Used when no AI provider is configured (AI_PROVIDER empty/'template' or no
 * API key present). Produces the same structured section output as a real
 * provider so the whole assistant flow works for testing without any keys.
 */
class TemplateProvider implements AiProviderInterface
{
    public function name(): string
    {
        return 'template';
    }

    public function label(): string
    {
        return 'Built-in Template (no API key)';
    }

    public function chat(array $messages, array $options = []): string
    {
        // Not used directly; AiAssistantService calls generateStructured().
        return '';
    }

    /**
     * Build a structured section map deterministically from context.
     *
     * @param string $action   one of AiAssistantService::ACTIONS keys
     * @param array<string,mixed> $ctx  subject_name, class_name, topic, week, level, ...
     * @param string[] $sections  which section keys to generate
     * @return array<string,string>
     */
    public function generateStructured(string $action, array $ctx, array $sections): array
    {
        $topic  = trim((string)($ctx['topic'] ?? 'The Topic'));
        $subject = trim((string)($ctx['subject_name'] ?? 'Subject'));
        $class  = trim((string)($ctx['class_name'] ?? 'Class'));
        $week   = trim((string)($ctx['week'] ?? '1'));
        $level  = trim((string)($ctx['level'] ?? ''));

        $out = [];
        foreach ($sections as $section) {
            $out[$section] = $this->buildSection($section, $topic, $subject, $class, $week, $level);
        }
        return $out;
    }

    private function buildSection(string $section, string $topic, string $subject, string $class, string $week, string $level): string
    {
        return match ($section) {
            'title' => $topic,
            'sub_topic' => 'Introduction to ' . $topic,
            'learning_objectives' => "By the end of this lesson, students should be able to:\n"
                . "- Define key terms and concepts related to $topic\n"
                . "- Explain the main ideas and principles of $topic\n"
                . "- Apply the concepts of $topic to practical examples and exercises\n"
                . "- Analyse and evaluate different aspects of $topic\n"
                . "- Create and present original work based on $topic",
            'blooms_objectives' => "Bloom's Taxonomy-aligned objectives for $topic:\n"
                . "Remember: Recall the key definitions and facts about $topic.\n"
                . "Understand: Explain the central concepts of $topic in your own words.\n"
                . "Apply: Use the principles of $topic to solve related problems.\n"
                . "Analyse: Break down $topic into parts and examine relationships between them.\n"
                . "Evaluate: Justify positions and compare strengths/weaknesses within $topic.\n"
                . "Create: Design a product, model, or presentation based on $topic.",
            'introduction' => "Begin by asking students what they already know about $topic. "
                . "Share a relatable real-world scenario or image connected to $topic to capture interest. "
                . "Pose a guiding question: 'What do you think makes $topic important in $subject?' "
                . "Then state the lesson objectives so students know what they will achieve by the end.",
            'previous_knowledge' => "Students have prior knowledge of basic concepts in $subject from earlier lessons. "
                . "They can recognise simple terms related to $topic and have completed introductory exercises. "
                . "Connect $topic to previously covered material to build on this foundation.",
            'body' => "LESSON CONTENT\n\n"
                . "1. Meaning and Background\n"
                . "$topic is a core area of $subject studied in $class. It builds on earlier lessons and forms the foundation for more advanced work.\n\n"
                . "2. Key Concepts\n"
                . "Define the main terms associated with $topic. Write clear, simple definitions on the board and have students copy them into their notebooks. Give one real-life example for each concept.\n\n"
                . "3. Main Points and Details\n"
                . "Explain the central ideas of $topic step by step. Use diagrams, charts, or demonstrations where possible. Emphasise the relationships between ideas and connect them to everyday experiences of the students.\n\n"
                . "4. Application and Examples\n"
                . "Work through 2–3 worked examples with the class. Then allow students to attempt similar exercises individually and in small groups. Provide immediate feedback and correct common misconceptions.\n\n"
                . "5. Summary of Key Points\n"
                . "Recap the main ideas of $topic. Ask students to state one new thing they learned and one area they still find difficult.",
            'summary' => "This lesson introduced $topic in $subject for $class. "
                . "Students explored the meaning, key concepts, and applications of $topic. "
                . "They practised applying the ideas to exercises and can now define core terms, explain the main points, and attempt related problems. "
                . "The next lesson will build on these foundations with more advanced applications.",
            'assignment' => "1. Write concise notes on $topic in your notebook.\n"
                . "2. Answer the practice questions on $topic provided by your teacher.\n"
                . "3. Find one real-life example of $topic and be ready to explain it to the class.\n"
                . "4. Prepare two questions about $topic for the next class discussion.",
            'reference_materials' => "- Recommended $subject textbook for $class\n"
                . "- Scheme of work for $subject\n"
                . "- Nigerian curriculum guide (NERDC) for $level\n"
                . "- Teacher's lesson notes and worksheets on $topic",
            'instructional_materials' => "- Whiteboard / interactive board and markers\n"
                . "- Textbook: $subject for $class\n"
                . "- Handout with key terms and diagrams for $topic\n"
                . "- Real objects / pictures related to $topic\n"
                . "- Worksheets for individual and group practice",
            'teaching_methods' => "- Interactive lecture with guided questioning\n"
                . "- Demonstration and modelling\n"
                . "- Group discussion and collaborative tasks\n"
                . "- Think-Pair-Share and peer teaching\n"
                . "- Formative assessment through oral questions and short tasks",
            'classroom_activities' => "- Teacher presents the key concepts of $topic using visuals and real examples.\n"
                . "- Teacher demonstrates worked examples step by step.\n"
                . "- Teacher organises students into groups and guides discussion on $topic.\n"
                . "- Teacher circulates to give feedback and address misconceptions.\n"
                . "- Teacher conducts a whole-class review and oral quiz on $topic.",
            'student_activities' => "- Students listen, take notes, and ask clarifying questions.\n"
                . "- Students answer oral questions and participate in discussions.\n"
                . "- Students complete practice exercises on $topic individually and in groups.\n"
                . "- Students present their answers to the class.\n"
                . "- Students write a short reflection on what they learned.",
            'assessment' => "- Oral questioning throughout the lesson to check understanding.\n"
                . "- Observation of participation during group work.\n"
                . "- Exit ticket: one thing learned and one question remaining.\n"
                . "- Review of completed practice exercises.\n"
                . "- Short quiz on $topic at the beginning of the next lesson.",
            'presentation_steps' => "Step 1 – Introduction (5 minutes): Introduce $topic, share the objectives, and link to prior knowledge.\n"
                . "Step 2 – Presentation of Key Concepts (10 minutes): Define and explain the main ideas of $topic with examples.\n"
                . "Step 3 – Guided Practice (10 minutes): Work through examples together as a class with probing questions.\n"
                . "Step 4 – Group/Independent Practice (10 minutes): Students apply $topic in groups or individually while you support.\n"
                . "Step 5 – Review and Assessment (5 minutes): Recap key points, address errors, and assign homework.",
            'duration' => '40 minutes',
            'remarks' => '',
            'objectives' => "By the end of this lesson on $topic, students should be able to:\n"
                . "- define and explain the key concepts of $topic\n"
                . "- describe the importance and applications of $topic in $subject\n"
                . "- solve basic problems related to $topic\n"
                . "- discuss how $topic connects to daily life",
            'instructional_materials' => (static function () use ($topic, $subject, $class, $level) {
                return "- Whiteboard / interactive board and markers\n"
                    . "- Textbook: $subject for $class\n"
                    . "- Handout with key terms for $topic\n"
                    . "- Real objects / pictures related to $topic\n"
                    . "- Nigerian curriculum guide (NERDC) for $level\n"
                    . "- Practice worksheets";
            })(),
            'class_activities' => (static function () use ($topic) {
                return "- Teacher presents the key concepts of $topic with visuals.\n"
                    . "- Teacher demonstrates worked examples.\n"
                    . "- Teacher guides group discussion and peer teaching on $topic.\n"
                    . "- Teacher provides feedback and clarifies misconceptions.\n"
                    . "- Teacher conducts oral review and short quiz on $topic.";
            })(),
            'student_activities' => "- Students take notes and ask questions.\n"
                . "- Students answer oral questions and join discussions.\n"
                . "- Students complete exercises on $topic individually and in pairs.\n"
                . "- Students present answers to the class.\n"
                . "- Students write a short reflection on the lesson.",
            'assignments' => (static function () use ($topic) {
                return "1. Write concise notes on $topic.\n"
                    . "2. Answer the practice questions on $topic.\n"
                    . "3. Find a real-life example of $topic and present it next lesson.";
            })(),
            'assignment' => "1. Write concise notes on $topic in your notebook.\n"
                . "2. Answer the practice questions on $topic.\n"
                . "3. Find one real-life example of $topic and be ready to explain it next lesson.",
            'quiz_questions' => json_encode([
                ['question' => "Which of the following best describes $topic?",
                 'options' => ['A. The main idea of this lesson', 'B. An unrelated concept', 'C. A historical event', 'D. A mathematical formula'],
                 'answer' => 'A', 'explanation' => "The lesson is centred on $topic, so option A is correct."],
                ['question' => "The concept of $topic is studied mainly in which subject?",
                 'options' => ['A. English Language', 'B. ' . $subject, 'C. Physical Education', 'D. French'],
                 'answer' => 'B', 'explanation' => "$topic belongs to the $subject curriculum."],
                ['question' => "A real-life application of $topic is:",
                 'options' => ['A. Using it to solve everyday problems', 'B. Ignoring it completely', 'C. Avoiding related tasks', 'D. None of the above'],
                 'answer' => 'A', 'explanation' => "Knowledge of $topic helps students apply concepts in daily life."],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'exam_questions' => json_encode([
                ['question' => "Define $topic and give one example.",
                 'type' => 'short_answer', 'marks' => 3,
                 'answer_guide' => "Student should state a clear definition and a relevant example of $topic."],
                ['question' => "Which of the following statements about $topic is TRUE?",
                 'type' => 'mcq', 'marks' => 2, 'options' => ['A. Option one', 'B. Option two', 'C. Option three', 'D. Option four'], 'answer' => 'A'],
                ['question' => "Explain two real-life applications of $topic.",
                 'type' => 'essay', 'marks' => 5,
                 'answer_guide' => "Student should mention two clear, relevant applications with brief explanations."],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'marking_guide' => "Marking guide for $topic\n"
                . "- Award 1 mark for each correct definition/term clearly stated.\n"
                . "- Award 2 marks for a correct, well-explained example of $topic.\n"
                . "- Award marks proportionately for partially correct answers.\n"
                . "- Deduct marks for irrelevant content only.\n"
                . "Suggested allocation: Definition (2), Explanation (3), Examples (3), Application (2) = 10 marks.",
            'teaching_methods' => "- Interactive lecture with guided questioning\n"
                . "- Demonstration and modelling\n"
                . "- Group discussion and collaborative tasks\n"
                . "- Think-Pair-Share and peer teaching\n"
                . "- Formative assessment through oral questions and short tasks",
            'summary' => "This lesson introduced $topic in $subject for $class. Students defined the key concepts, "
                . "explored the main points, and applied them to exercises. They can now explain $topic and attempt related problems. "
                . "The next lesson will extend these ideas further.",
            'differentiation' => "Differentiation for $topic\n"
                . "For slow learners: Provide simplified definitions, pair them with supportive peers, use visual aids and step-by-step worked examples, and allow extra time on practice tasks.\n"
                . "For advanced learners: Provide extension tasks, open-ended challenges, opportunities to lead group work, and research tasks that explore $topic in greater depth.",
            'curriculum_alignment' => "Curriculum alignment for $topic\n"
                . "- Subject: $subject\n"
                . "- Level: $level (BECE / WAEC track)\n"
                . "- Nigerian curriculum: NERDC basic education curriculum for $level.\n"
                . "- Examination focus: objectives and content map to BECE (JSS) and WAEC/NECO (SS) syllabi where applicable.\n"
                . "- Suggested week in scheme of work: Week $week.",
            default => 'Generated content for ' . $section . ' on ' . $topic . '.',
        };
    }
}
