<?php
namespace App\Contracts;

/**
 * Provider-agnostic contract for the AI teaching assistant.
 *
 * Implementations wrap a concrete LLM API (OpenAI, Anthropic, Google Gemini,
 * or the local template fallback). The rest of the application only depends
 * on this interface, so new providers can be added without touching app code.
 */
interface AiProviderInterface
{
    /** Stable provider identifier stored in ai_generation_log. */
    public function name(): string;

    /** Human-readable provider/model label for the UI. */
    public function label(): string;

    /**
     * Send a chat-style message list and return the assistant text reply.
     *
     * @param array<int,array{role:string,content:string}> $messages
     * @param array<string,mixed> $options  e.g. temperature, max_tokens
     */
    public function chat(array $messages, array $options = []): string;
}
