<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIChatController extends Controller
{
    protected string $groqUrl;
    protected string $model;
    protected int $timeout;

    public function __construct()
    {
        $this->groqUrl = config('services.groq.base_url');
        $this->model = config('services.groq.model', 'llama-3.1-8b-instant');
        $this->timeout = config('services.groq.timeout', 30);
    }

    /**
     * Chat với AI Assistant (RAG-style grounding)
     * POST /api/ai/chat
     */
    public function chat(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'context_type' => 'nullable|string|in:flood,rescue,evacuation,general',
        ]);

        $userMessage = trim($validated['message']);
        $contextType = $validated['context_type'] ?? 'general';

        try {
            $systemPrompt = $this->buildSystemPrompt($contextType);
            $contextData = $this->fetchContextData($contextType);

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ];

            $apiKey = config('services.groq.api_key');

            if (empty($apiKey)) {
                return $this->fallbackResponse($userMessage, $contextData);
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->groqUrl . '/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.3,
                    'max_tokens' => 500,
                ]);

            if (!$response->successful()) {
                Log::error('Groq API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return $this->fallbackResponse($userMessage, $contextData);
            }

            $data = $response->json();
            $reply = $data['choices'][0]['message']['content'] ?? 'Xin lỗi, tôi không thể trả lời lúc này.';

            return ApiResponse::success([
                'reply' => trim($reply),
                'model' => $this->model,
                'context_type' => $contextType,
                'sources' => $contextData['sources'] ?? [],
            ]);

        } catch (\Exception $e) {
            Log::error('AI Chat error', ['error' => $e->getMessage()]);
            return $this->fallbackResponse($userMessage, $contextData);
        }
    }

    /**
     * Build system prompt với domain knowledge
     */
    protected function buildSystemPrompt(string $contextType): string
    {
        $base = <<<'PROMPT'
You are AegisFlow AI Assistant — an expert emergency management AI assistant for Da Nang, Vietnam.
You help rescue operators, city administrators, and citizens during flood emergencies.

IMPORTANT RULES:
- Respond in the same language as the user (Vietnamese or English)
- Be concise, action-oriented, and prioritize life safety
- Always include confidence levels when making recommendations
- Flag critical situations immediately with 🚨
- Never fabricate data — only use information provided in the context
- If data is insufficient, say "I don't have enough data to answer accurately"
- All recommendations must emphasize: AI advises, humans decide
PROMPT;

        $domain = match ($contextType) {
            'flood' => <<<'EXTRA'
You specialize in FLOOD PREDICTION & MONITORING.
Da Nang flood context:
- Key flood zones: An Khê (Thanh Khê), Hòa Khánh (Liên Chiểu), Túy Loan (Hòa Vang)
- VNMHA alert thresholds: water level > 1.5m (warning), > 3.0m (danger), > 5.0m (critical)
- Rainfall thresholds: > 50mm/24h (watch), > 150mm/24h (warning), > 250mm/24h (critical)
- Tide impact: High tide (> 1.5m) amplifies inland flooding in low-lying areas
- Common flood season: October-December (typhoon season), May-July (summer storms)
EXTRA,
            'rescue' => <<<'EXTRA'
You specialize in RESCUE COORDINATION.
Da Nang rescue context:
- Rescue priority factors: urgency (30pts), vulnerable groups (elderly/disabled/pregnant +25pts),
  people count (15pts), water level (15pts), wait time (10pts), incident flag (5pts)
- Typical rescue team response time target: < 15 minutes in urban areas
- Vulnerable groups: elderly (65+), disabled, children (<12), pregnant women
- Shelter capacity: varies 50-500 people per shelter
- Emergency contacts: 113 (police), 114 (fire), 115 (ambulance)
EXTRA,
            'evacuation' => <<<'EXTRA'
You specialize in EVACUATION ROUTING.
Da Nang evacuation context:
- Average walking speed in emergency: 4 km/h (~1.11 m/s)
- Evacuation routes must avoid flood zones (polygon check)
- Safe zones: elevated areas (>10m elevation), designated shelters
- Key shelters: Duy Tan University, Tam Thuận Community Center, Hòa Vang School
- ETA calculation: route distance / walking speed + safety buffer
- Safe route safety score: 0.7-1.0 (1.0 = safest, avoid flood zones completely)
EXTRA,
            default => <<<'EXTRA'
You answer general questions about AegisFlow AI system.
AegisFlow AI = Early Warning + Smart Dispatch + Safe Evacuation in one platform.
Tech stack: Laravel (backend), Next.js (dashboard), FastAPI (AI), React Native (mobile).
EXTRA,
        };

        return $base . "\n\n" . $domain;
    }

    /**
     * Fetch real-time context data cho RAG-style grounding
     */
    protected function fetchContextData(string $contextType): array
    {
        $data = ['sources' => []];

        try {
            $sensorUrl = config('services.ai.url', 'http://localhost:5005');
            $sensorResponse = Http::timeout(5)->get($sensorUrl . '/api/sensors/recent');

            if ($sensorResponse->successful()) {
                $data['sensor_readings'] = $sensorResponse->json();
                $data['sources'][] = 'ai_service:sensors';
            }
        } catch (\Exception $e) {
            $data['sensor_error'] = $e->getMessage();
        }

        try {
            $predictionUrl = config('services.ai.url', 'http://localhost:5005');
            $predResponse = Http::timeout(5)->get($predictionUrl . '/api/predictions/recent');

            if ($predResponse->successful()) {
                $data['recent_predictions'] = $predResponse->json();
                $data['sources'][] = 'ai_service:predictions';
            }
        } catch (\Exception $e) {
            // Silently fail — predictions may not exist yet
        }

        return $data;
    }

    /**
     * Fallback response khi Groq API unavailable
     */
    protected function fallbackResponse(string $userMessage, array $contextData): ApiResponse
    {
        $lowerMsg = mb_strtolower($userMessage);

        $replies = [
            'flood' => [
                'ngập' => '🌊 Dựa trên dữ liệu hiện tại, mực nước và lượng mưa được theo dõi liên tục. Vui lòng kiểm tra Dashboard để xem chi tiết các vùng ngập active.',
                'mưa' => '🌧️ Hệ thống đang theo dõi lượng mưa tại 93 trạm đo. Các trạm cảnh báo sớm được phân bố khắp các quận huyện của Đà Nẵng.',
                'cảnh báo' => '🚨 Để xem cảnh báo ngập hiện tại, vui lòng truy cập trang Alerts trên Dashboard hoặc ứng dụng di động.',
                'default' => '📊 Tôi đang ở chế độ offline. Vui lòng kiểm tra Dashboard để biết thông tin flood monitoring thời gian thực.',
            ],
            'rescue' => [
                'cứu' => '🚑 Hệ thống đang theo dõi các yêu cầu cứu hộ. Ưu tiên: người cao tuổi, khuyết tật, trẻ em, phụ nữ mang thai.',
                'đội' => '🚒 Các đội cứu hộ được phân bổ theo khu vực. Vui lòng kiểm tra Rescue Teams trên Dashboard.',
                'ưu tiên' => '📋 Tiêu chí ưu tiên cứu hộ: mức độ khẩn cấp (30đ), nhóm yếu thế (25đ), số người (15đ), mực nước (15đ), thời gian chờ (10đ).',
                'default' => '🚨 Để xem chi tiết điều phối cứu hộ, vui lòng truy cập trang Rescue Requests trên Dashboard.',
            ],
            'evacuation' => [
                'sơ tán' => '🏃 Tuyến sơ tán an toàn được tính toán tránh các vùng ngập. Tốc độ đi bộ khẩn cấp: 4 km/h.',
                'tuyến' => '🗺️ Hệ thống đang tối ưu hóa tuyến đường sơ tán. Vui lòng kiểm tra bản đồ để xem tuyến an toàn.',
                'nơi trú' => '🏠 Các điểm trú ẩn an toàn: Trường Đại học Duy Tân, Trung tâm Cộng đồng Tam Thuận, Trường học Hòa Vang.',
                'default' => '🗺️ Để xem tuyến sơ tán an toàn, vui lòng truy cập bản đồ Evacuation trên Dashboard.',
            ],
            'general' => [
                'default' => '🤖 Tôi là trợ lý AI của AegisFlow. Để được hỗ trợ cụ thể, vui lòng hỏi về: ngập lụt, cứu hộ, sơ tán, hoặc cảnh báo.',
            ],
        ];

        $reply = '📊 Hiện tại tôi đang offline. Vui lòng truy cập Dashboard để biết thông tin chi tiết.';

        foreach ($replies as $type => $patterns) {
            foreach ($patterns as $keyword => $response) {
                if ($keyword !== 'default' && str_contains($lowerMsg, $keyword)) {
                    return ApiResponse::success([
                        'reply' => $response,
                        'model' => 'fallback',
                        'context_type' => $type,
                        'sources' => ['fallback'],
                        'offline' => true,
                    ]);
                }
            }
        }

        return ApiResponse::success([
            'reply' => $replies['general']['default'],
            'model' => 'fallback',
            'context_type' => 'general',
            'sources' => ['fallback'],
            'offline' => true,
        ]);
    }

    /**
     * Get AI system status
     * GET /api/ai/status
     */
    public function status()
    {
        $apiKey = config('services.groq.api_key');
        $hasApi = !empty($apiKey);

        $aiOnline = false;
        $aiLatency = null;

        if ($hasApi) {
            try {
                $start = microtime(true);
                $response = Http::timeout(5)
                    ->withHeaders(['Authorization' => 'Bearer ' . $apiKey])
                    ->post($this->groqUrl . '/chat/completions', [
                        'model' => $this->model,
                        'messages' => [['role' => 'user', 'content' => 'ping']],
                        'max_tokens' => 5,
                    ]);
                $aiLatency = round((microtime(true) - $start) * 1000);
                $aiOnline = $response->successful();
            } catch (\Exception $e) {
                $aiOnline = false;
            }
        }

        return ApiResponse::success([
            'groq_configured' => $hasApi,
            'model' => $this->model,
            'ai_online' => $aiOnline,
            'latency_ms' => $aiLatency,
        ]);
    }
}
