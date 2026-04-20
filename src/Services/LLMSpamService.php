<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\Shop;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * Optionaler LLM-Zweitpruefer fuer den Smart-Spamfilter.
 *
 * Unterstuetzt: Ollama (lokal, kein Key), OpenAI, Anthropic Claude, Google Gemini.
 *
 * Der Service fragt das LLM mit einem strikten Klassifier-Prompt und
 * erwartet eine einzeilige JSON-Antwort der Form:
 *   {"spam": true|false, "confidence": 0.0..1.0, "reason": "..."}
 *
 * Fehler (Timeout, Netzfehler, Parse-Fehler) werden als "nicht gepruefft" behandelt
 * und geben ein fail-open Ergebnis zurueck (spam = false, error = "..."),
 * damit der regelbasierte Score maßgeblich bleibt.
 */
class LLMSpamService
{
    public const PROVIDER_NONE   = 'none';
    public const PROVIDER_OLLAMA = 'ollama';
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_CLAUDE = 'claude';
    public const PROVIDER_GEMINI = 'gemini';

    private const DEFAULT_TIMEOUT = 8;
    private const MAX_INPUT_CHARS = 3000;

    /** Default-Modelle pro Provider (vom Admin ueberschreibbar) */
    private const DEFAULT_MODELS = [
        self::PROVIDER_OLLAMA => 'llama3.2',
        self::PROVIDER_OPENAI => 'gpt-4o-mini',
        self::PROVIDER_CLAUDE => 'claude-haiku-4-5-20251001',
        self::PROVIDER_GEMINI => 'gemini-1.5-flash-latest',
    ];

    private Setting $settings;

    public function __construct(Setting $settings)
    {
        $this->settings = $settings;
    }

    public function isEnabled(): bool
    {
        if (!$this->settings->getBool('llm_enabled')) {
            return false;
        }
        $provider = $this->getProvider();
        if ($provider === self::PROVIDER_NONE) {
            return false;
        }
        // Ollama braucht keinen API-Key, alle anderen schon
        if ($provider !== self::PROVIDER_OLLAMA && $this->settings->get('llm_api_key') === '') {
            return false;
        }
        return true;
    }

    public function getProvider(): string
    {
        $p = $this->settings->get('llm_provider', self::PROVIDER_NONE);
        $allowed = [
            self::PROVIDER_NONE,
            self::PROVIDER_OLLAMA,
            self::PROVIDER_OPENAI,
            self::PROVIDER_CLAUDE,
            self::PROVIDER_GEMINI,
        ];
        return in_array($p, $allowed, true) ? $p : self::PROVIDER_NONE;
    }

    /**
     * Kurzer Ping-Test, z.B. "Is this spam: 'viagra buy now'"
     *
     * @return array{success: bool, message: string, result?: array}
     */
    public function testConnection(): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Kein Anbieter konfiguriert'];
        }

        $result = $this->classify('Buy cheap viagra now, click http://spam.xyz free money casino jackpot!');
        if (isset($result['error'])) {
            return ['success' => false, 'message' => $result['error']];
        }
        return [
            'success' => true,
            'message' => 'Verbindung erfolgreich',
            'result'  => $result,
        ];
    }

    /**
     * Klassifiziert einen Text als Spam oder Ham.
     *
     * @return array{spam: bool, confidence: float, reason: string, provider: string, model: string, error?: string}
     */
    public function classify(string $text): array
    {
        $text = trim(mb_substr($text, 0, self::MAX_INPUT_CHARS));
        if ($text === '') {
            return [
                'spam'       => false,
                'confidence' => 0.0,
                'reason'     => 'Empty text',
                'provider'   => $this->getProvider(),
                'model'      => $this->getModel(),
            ];
        }

        $provider = $this->getProvider();
        $model    = $this->getModel();

        try {
            $raw = match ($provider) {
                self::PROVIDER_OLLAMA => $this->callOllama($text, $model),
                self::PROVIDER_OPENAI => $this->callOpenAi($text, $model),
                self::PROVIDER_CLAUDE => $this->callClaude($text, $model),
                self::PROVIDER_GEMINI => $this->callGemini($text, $model),
                default => throw new \RuntimeException('No provider configured'),
            };
        } catch (\Throwable $e) {
            $this->logError($provider, $e->getMessage());
            return $this->failOpen($provider, $model, $e->getMessage());
        }

        $parsed = $this->parseJsonResponse($raw);
        if ($parsed === null) {
            return $this->failOpen($provider, $model, 'Invalid JSON from LLM');
        }

        return [
            'spam'       => (bool)($parsed['spam'] ?? false),
            'confidence' => max(0.0, min(1.0, (float)($parsed['confidence'] ?? 0.5))),
            'reason'     => mb_substr((string)($parsed['reason'] ?? ''), 0, 500),
            'provider'   => $provider,
            'model'      => $model,
        ];
    }

    // ─── Provider-Aufrufe ──────────────────────────────────────

    private function callOllama(string $text, string $model): string
    {
        $endpoint = $this->validateOllamaEndpoint(
            $this->settings->get('llm_endpoint', 'http://localhost:11434')
        );
        $endpoint = rtrim($endpoint, '/') . '/api/generate';

        $body = [
            'model'  => $model,
            'prompt' => $this->buildPrompt($text),
            'stream' => false,
            'format' => 'json',
            'options' => [
                'temperature' => 0.1,
                'num_predict' => 200,
            ],
        ];

        $resp = $this->httpPostJson($endpoint, $body, []);
        $json = json_decode($resp, true);
        if (!is_array($json) || !isset($json['response'])) {
            throw new \RuntimeException('Ollama: unerwartete Antwort');
        }
        return (string)$json['response'];
    }

    private function callOpenAi(string $text, string $model): string
    {
        $apiKey = $this->settings->get('llm_api_key');
        if ($apiKey === '') {
            throw new \RuntimeException('OpenAI: API-Key fehlt');
        }

        $body = [
            'model'       => $model,
            'temperature' => 0.1,
            'max_tokens'  => 200,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user',   'content' => $this->userPrompt($text)],
            ],
        ];

        $resp = $this->httpPostJson(
            'https://api.openai.com/v1/chat/completions',
            $body,
            ['Authorization: Bearer ' . $apiKey]
        );
        $json = json_decode($resp, true);
        $content = $json['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            throw new \RuntimeException('OpenAI: unerwartete Antwort');
        }
        return $content;
    }

    private function callClaude(string $text, string $model): string
    {
        $apiKey = $this->settings->get('llm_api_key');
        if ($apiKey === '') {
            throw new \RuntimeException('Claude: API-Key fehlt');
        }

        $body = [
            'model'      => $model,
            'max_tokens' => 200,
            'temperature' => 0.1,
            'system'     => $this->systemPrompt(),
            'messages'   => [
                ['role' => 'user', 'content' => $this->userPrompt($text)],
            ],
        ];

        $resp = $this->httpPostJson(
            'https://api.anthropic.com/v1/messages',
            $body,
            [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ]
        );
        $json = json_decode($resp, true);
        $content = $json['content'][0]['text'] ?? null;
        if (!is_string($content)) {
            throw new \RuntimeException('Claude: unerwartete Antwort');
        }
        return $content;
    }

    private function callGemini(string $text, string $model): string
    {
        $apiKey = $this->settings->get('llm_api_key');
        if ($apiKey === '') {
            throw new \RuntimeException('Gemini: API-Key fehlt');
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

        $body = [
            'systemInstruction' => [
                'parts' => [['text' => $this->systemPrompt()]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $this->userPrompt($text)]],
            ]],
            'generationConfig' => [
                'temperature'      => 0.1,
                'maxOutputTokens'  => 200,
                'responseMimeType' => 'application/json',
            ],
        ];

        $resp = $this->httpPostJson($url, $body, []);
        $json = json_decode($resp, true);
        $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!is_string($content)) {
            throw new \RuntimeException('Gemini: unerwartete Antwort');
        }
        return $content;
    }

    // ─── Helpers ───────────────────────────────────────────────

    /**
     * Minimal-Validierung der admin-konfigurierten Ollama-Endpoint-URL.
     * Blockt "javascript:", "file://", fehlende Hosts etc. — voll-aggressive
     * SSRF-Abwehr macht hier keinen Sinn, weil Admins legitime interne
     * Ollama-Setups (Docker, LAN) brauchen und das Threat-Model "trusted admin" ist.
     * Default-Wert (localhost:11434) ist immer zulaessig.
     */
    private function validateOllamaEndpoint(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return 'http://localhost:11434';
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('Ollama: invalid endpoint URL');
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \RuntimeException('Ollama: endpoint scheme must be http or https');
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new \RuntimeException('Ollama: endpoint host missing');
        }
        return $url;
    }

    private function systemPrompt(): string
    {
        return 'You are a strict spam classifier for contact-form and comment submissions in a German/English e-commerce shop. '
             . 'Respond ONLY with a single compact JSON object, no prose, no markdown. '
             . 'Format: {"spam": boolean, "confidence": 0.0-1.0, "reason": "short english reason"}. '
             . 'Mark as spam: SEO/backlink solicitations, crypto/casino/adult promotions, bulk URLs, gibberish, '
             . 'obvious phishing, mass-generated outreach. '
             . 'Do NOT mark as spam: legitimate product questions, support requests, complaints, feedback — '
             . 'even if poorly written or in a foreign language.';
    }

    private function userPrompt(string $text): string
    {
        return "Classify this submission:\n\n---\n" . $text . "\n---";
    }

    private function buildPrompt(string $text): string
    {
        return $this->systemPrompt() . "\n\n" . $this->userPrompt($text);
    }

    private function parseJsonResponse(string $raw): ?array
    {
        $raw = trim($raw);
        // Oft liefern LLMs trotz Instruktionen Markdown-Fences
        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
            $raw = preg_replace('/\s*```$/', '', (string)$raw);
            $raw = trim((string)$raw);
        }
        // Einzelnes JSON-Objekt extrahieren
        if (!str_starts_with($raw, '{')) {
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $raw = $m[0];
            } else {
                return null;
            }
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function getModel(): string
    {
        $model = trim($this->settings->get('llm_model'));
        if ($model !== '') {
            return $model;
        }
        return self::DEFAULT_MODELS[$this->getProvider()] ?? '';
    }

    private function getTimeout(): int
    {
        $t = $this->settings->getInt('llm_timeout', self::DEFAULT_TIMEOUT);
        return max(2, min(60, $t));
    }

    /**
     * @param array<int, string> $extraHeaders
     */
    private function httpPostJson(string $url, array $body, array $extraHeaders): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }

        $headers = array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: bbfdesign-captcha/1.0',
        ], $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->getTimeout(),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            throw new \RuntimeException('HTTP: ' . $err);
        }
        if ($code < 200 || $code >= 300) {
            $snippet = mb_substr((string)$resp, 0, 300);
            throw new \RuntimeException('HTTP ' . $code . ': ' . $snippet);
        }
        return (string)$resp;
    }

    /**
     * @return array{spam: bool, confidence: float, reason: string, provider: string, model: string, error: string}
     */
    private function failOpen(string $provider, string $model, string $error): array
    {
        return [
            'spam'       => false,
            'confidence' => 0.0,
            'reason'     => '',
            'provider'   => $provider,
            'model'      => $model,
            'error'      => $error,
        ];
    }

    private function logError(string $provider, string $msg): void
    {
        if (!$this->settings->getBool('debug_mode')) {
            return;
        }
        try {
            Shop::Container()->getLogService()->warning(
                'BBF Captcha LLM (' . $provider . '): ' . $msg
            );
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
