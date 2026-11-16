<?php

declare(strict_types=1);

namespace DocuGest\Engine;

/**
 * Memoria conversacional persistente por usuario para DocuGest 2.0.
 * Permite repreguntas con contexto en hilo manteniendo bajo consumo de tokens
 * mediante compresión de turnos anteriores.
 */
final class DocuGestMemoryManager
{
    private const MAX_TURNS_SAVED = 10;
    private const MAX_TURNS_IN_PROMPT = 4;

    private string $userDir;

    public function __construct(string $username)
    {
        $safeUser = preg_replace('/[^a-zA-Z0-9_.-]/', '_', strtolower(trim($username) ?: 'anon'));
        $this->userDir = dirname(__DIR__, 2) . '/storage/users/' . $safeUser;
        if (!is_dir($this->userDir)) {
            @mkdir($this->userDir, 0775, true);
        }
    }

    private function getConversationFile(string $conversationId): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($conversationId)) ?: 'default';
        return $this->userDir . '/conv_' . $safeId . '.json';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function loadTurns(string $conversationId): array
    {
        $file = $this->getConversationFile($conversationId);
        if (!is_file($file)) {
            return [];
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['turns']) || !is_array($decoded['turns'])) {
            return [];
        }

        return $decoded['turns'];
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     */
    public function appendTurn(
        string $conversationId,
        string $question,
        string $answer,
        array $sources = []
    ): void {
        $turns = $this->loadTurns($conversationId);
        $sourceIds = array_values(array_unique(array_filter(array_map(
            static fn($s) => isset($s['id']) ? (int)$s['id'] . ':' . (string)($s['titulo'] ?? '') : null,
            $sources
        ))));

        $turns[] = [
            'timestamp' => date('c'),
            'question'  => $question,
            'answer'    => mb_substr(trim($answer), 0, 2000, 'UTF-8'),
            'sources'   => $sourceIds,
        ];

        if (count($turns) > self::MAX_TURNS_SAVED) {
            $turns = array_slice($turns, -self::MAX_TURNS_SAVED);
        }

        $payload = [
            'conversation_id' => $conversationId,
            'updated_at'      => date('c'),
            'turns'           => $turns,
        ];

        @file_put_contents(
            $this->getConversationFile($conversationId),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    public function clearConversation(string $conversationId): void
    {
        $file = $this->getConversationFile($conversationId);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Construye el bloque de historial compacto para inyectar en el prompt cuando es turno >= 2.
     */
    public function buildCompactHistoryBlock(string $conversationId): string
    {
        $turns = $this->loadTurns($conversationId);
        if (empty($turns)) {
            return '';
        }

        $recent = array_slice($turns, -self::MAX_TURNS_IN_PROMPT);
        $lines = ["<MEMORIA_CONVERSACIONAL_RECIENTE>"];
        foreach ($recent as $idx => $t) {
            $n = $idx + 1;
            $q = trim((string)($t['question'] ?? ''));
            $a = preg_replace('/\s+/u', ' ', trim((string)($t['answer'] ?? '')));
            $a = mb_substr($a ?? '', 0, 420, 'UTF-8');
            $src = !empty($t['sources']) ? ' [Fuentes: ' . implode(', ', $t['sources']) . ']' : '';
            $lines[] = "Turno {$n} - Usuario: {$q}";
            $lines[] = "Turno {$n} - Asistente: {$a}{$src}";
        }
        $lines[] = "</MEMORIA_CONVERSACIONAL_RECIENTE>";
        return implode("\n", $lines);
    }
}
