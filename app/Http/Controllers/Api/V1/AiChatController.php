<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Category;
use App\Models\Item;
use App\Models\Store;
use App\Services\CropCycleAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Customer AI Assistant endpoint compatible with the React ChatBot UI.
 * Searches live catalog items (including crops currently farming for pre-order,
 * harvest-ready produce, and backyard-grown farms) cleanly on PostgreSQL.
 */
class AiChatController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer',
            'guest_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user = auth('api')->user();
        $guestId = $user ? null : ($request->input('guest_id') ? (string) $request->input('guest_id') : null);

        if (! $user && ! $guestId) {
            return response()->json([
                'errors' => [['code' => 'identity', 'message' => 'Provide either authentication token or guest_id.']],
            ], 403);
        }

        $moduleId = $request->header('moduleId') ? (int) $request->header('moduleId') : null;
        $zoneIds = $this->parseZoneIds($request);
        $primaryZoneId = $zoneIds[0] ?? null;

        $tablesReady = $this->hasAiChatTables();
        $conversation = $tablesReady
            ? $this->resolveConversation($request, $user?->id, $guestId, $moduleId, $primaryZoneId)
            : null;

        if ($conversation) {
            $moduleId = $moduleId ?: ($conversation->module_id ? (int) $conversation->module_id : null);
            if (empty($zoneIds) && $conversation->zone_id) {
                $zoneIds = [(int) $conversation->zone_id];
            }

            AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $request->input('message'),
            ]);
        }

        $reply = $this->buildCatalogAssistantReply($request->input('message'), $moduleId, $zoneIds);

        if ($conversation) {
            AiMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $reply['message'],
                'tool_name' => $reply['tool_name'],
                'metadata' => $reply['metadata'],
            ]);

            if (! $conversation->title) {
                $conversation->title = Str::limit(trim($request->input('message')), 60);
            }
            $conversation->touch();
            $conversation->save();
        }

        return response()->json([
            'conversation_id' => $conversation?->id ?? 0,
            'role' => 'assistant',
            'content' => $reply['message'],
            'tool_name' => $reply['tool_name'],
            'metadata' => $reply['metadata'],
        ], 200);
    }

    public function conversations(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $guestId = $user ? null : ($request->input('guest_id') ? (string) $request->input('guest_id') : null);

        if (! $user && ! $guestId) {
            return response()->json([
                'errors' => [['code' => 'identity', 'message' => 'Provide either authentication token or guest_id.']],
            ], 403);
        }

        if (! $this->hasAiChatTables()) {
            return response()->json(['total_size' => 0, 'data' => []], 200);
        }

        $conversations = AiConversation::where('status', 'active')
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->when(! $user && $guestId, fn ($q) => $q->where('guest_id', $guestId))
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return response()->json([
            'total_size' => $conversations->count(),
            'data' => $conversations,
        ], 200);
    }

    public function messages(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|integer',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:1',
            'guest_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user = auth('api')->user();
        $guestId = $user ? null : ($request->input('guest_id') ? (string) $request->input('guest_id') : null);

        if (! $user && ! $guestId) {
            return response()->json([
                'errors' => [['code' => 'identity', 'message' => 'Provide either authentication token or guest_id.']],
            ], 403);
        }

        if (! $this->hasAiChatTables()) {
            return response()->json([
                'total_size' => 0,
                'limit' => (int) $request->input('limit', 50),
                'offset' => (int) $request->input('offset', 1),
                'conversation_id' => (int) $request->input('conversation_id'),
                'data' => [],
            ], 200);
        }

        $conversation = $this->findConversation((int) $request->input('conversation_id'), $user?->id, $guestId);
        if (! $conversation) {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Conversation not found.']]], 404);
        }

        $limit = (int) $request->input('limit', 50);
        $offset = (int) $request->input('offset', 1);

        $messages = AiMessage::where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->paginate($limit, ['*'], 'page', $offset);

        return response()->json([
            'total_size' => $messages->total(),
            'limit' => $limit,
            'offset' => $offset,
            'conversation_id' => $conversation->id,
            'data' => $messages->items(),
        ], 200);
    }

    public function deleteConversation(Request $request, int $id): JsonResponse
    {
        $user = auth('api')->user();
        $guestId = $user ? null : ($request->input('guest_id') ? (string) $request->input('guest_id') : null);

        if (! $user && ! $guestId) {
            return response()->json([
                'errors' => [['code' => 'identity', 'message' => 'Provide either authentication token or guest_id.']],
            ], 403);
        }

        if (! $this->hasAiChatTables()) {
            return response()->json(['message' => 'Conversation archived successfully.'], 200);
        }

        $conversation = $this->findConversation($id, $user?->id, $guestId);
        if (! $conversation) {
            return response()->json(['errors' => [['code' => 'not_found', 'message' => 'Conversation not found.']]], 404);
        }

        $conversation->update(['status' => 'archived']);

        return response()->json(['message' => 'Conversation archived successfully.'], 200);
    }

    private function hasAiChatTables(): bool
    {
        return (bool) Cache::remember('schema_has_ai_chat_tables_v1', now()->addMinutes(5), function () {
            return Schema::hasTable('ai_conversations') && Schema::hasTable('ai_messages');
        });
    }

    private function findConversation(int $id, ?int $userId, ?string $guestId): ?AiConversation
    {
        return AiConversation::where('id', $id)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(! $userId && $guestId, fn ($q) => $q->where('guest_id', $guestId))
            ->first();
    }

    private function resolveConversation(Request $request, ?int $userId, ?string $guestId, ?int $moduleId, ?int $zoneId): AiConversation
    {
        $conversationId = (int) $request->input('conversation_id');
        if ($conversationId > 0) {
            $existing = $this->findConversation($conversationId, $userId, $guestId);
            if ($existing && $existing->status === 'active') {
                return $existing;
            }
        }

        return AiConversation::create([
            'user_id' => $userId,
            'guest_id' => $guestId,
            'module_id' => $moduleId,
            'zone_id' => $zoneId,
            'status' => 'active',
        ]);
    }

    private function parseZoneIds(Request $request): array
    {
        $raw = $request->header('zoneId');
        if (! $raw) {
            return [];
        }
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('intval', $decoded)));
        }
        if (is_numeric($raw)) {
            return [(int) $raw];
        }

        return [];
    }

    private function buildCatalogAssistantReply(string $prompt, ?int $moduleId, array $zoneIds): array
    {
        $clean = trim($prompt);
        $lower = strtolower($clean);

        $stopWords = ['find', 'show', 'me', 'want', 'need', 'buy', 'looking', 'for', 'any', 'have', 'do', 'you', 'the', 'a', 'an', 'in', 'from', 'to', 'price', 'how', 'much', 'what', 'which', 'is', 'are', 'available', 'book', 'preorder', 'pre-order', 'ahead', 'farming', 'ready'];
        $terms = collect(preg_split('/[^a-z0-9]+/i', $lower))
            ->filter(fn ($t) => strlen($t) >= 3 && ! in_array($t, $stopWords, true))
            ->take(5)
            ->values();

        $wantsPreOrder = str_contains($lower, 'pre-order') || str_contains($lower, 'preorder') || str_contains($lower, 'book ahead') || str_contains($lower, 'farming');
        $wantsBackyard = str_contains($lower, 'backyard');

        $productsQuery = Item::active()
            ->with(['store'])
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->when(! empty($zoneIds), fn ($q) => $q->whereHas('store', fn ($sq) => $sq->whereIn('zone_id', $zoneIds)));

        if ($wantsPreOrder && Schema::hasColumn('items', 'farming_status')) {
            $productsQuery->whereIn('farming_status', ['farming', 'harvest_ready']);
        }
        if ($wantsBackyard && Schema::hasColumn('items', 'is_backyard')) {
            $productsQuery->where('is_backyard', true);
        }

        if ($terms->isNotEmpty()) {
            $productsQuery->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhereRaw('LOWER(name) LIKE ?', ['%' . $term . '%'])
                        ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ['%' . $term . '%']);
                }
            });
        }

        $items = $productsQuery->orderByDesc('order_count')->limit(5)->get();

        $stores = Store::active()
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->when(! empty($zoneIds), fn ($q) => $q->whereIn('zone_id', $zoneIds))
            ->when($wantsBackyard && Schema::hasColumn('stores', 'is_backyard'), fn ($q) => $q->where('is_backyard', true))
            ->when($terms->isNotEmpty(), function ($q) use ($terms) {
                $q->where(function ($sq) use ($terms) {
                    foreach ($terms as $term) {
                        $sq->orWhereRaw('LOWER(name) LIKE ?', ['%' . $term . '%']);
                    }
                });
            })
            ->limit(4)
            ->get();

        $categories = Category::where('status', 1)
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->when($terms->isNotEmpty(), function ($q) use ($terms) {
                $q->where(function ($cq) use ($terms) {
                    foreach ($terms as $term) {
                        $cq->orWhereRaw('LOWER(name) LIKE ?', ['%' . $term . '%']);
                    }
                });
            })
            ->limit(4)
            ->get(['id', 'name', 'slug', 'image']);

        $formattedProducts = $items->map(function ($item) {
            $lifecycle = CropCycleAnalyticsService::analyze($item);

            return [
                'id' => (int) $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'price' => (float) $item->price,
                'image_full_url' => $item->image_full_url,
                'store_id' => (int) $item->store_id,
                'store_name' => $item->store?->name,
                'module_id' => (int) $item->module_id,
                'farming_status' => $lifecycle['effective_farming_status'],
                'expected_harvest_date' => $lifecycle['expected_harvest_date'],
                'pre_order_available' => $lifecycle['pre_order_available'],
            ];
        })->values()->all();

        $formattedStores = $stores->map(fn ($store) => [
            'id' => (int) $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'logo_full_url' => $store->logo_full_url,
            'module_id' => (int) $store->module_id,
            'zone_id' => (int) $store->zone_id,
        ])->values()->all();

        $lines = [];
        if (! empty($formattedProducts)) {
            $lines[] = 'Here are matching produce and farm listings on Aduanefie:';
            foreach ($formattedProducts as $p) {
                $tag = $p['farming_status'] === 'farming'
                    ? ($p['pre_order_available'] ? ' (Currently Farming — Pre-Order Available)' : ' (Currently Farming)')
                    : ($p['farming_status'] === 'harvest_ready' ? ' (Harvest Ready)' : '');
                $lines[] = sprintf('• %s — %s%s%s', $p['name'], Helpers::format_currency($p['price']), $p['store_name'] ? " from {$p['store_name']}" : '', $tag);
            }
        } elseif (! empty($formattedStores)) {
            $lines[] = 'Here are matching verified farms & suppliers on Aduanefie:';
            foreach ($formattedStores as $s) {
                $lines[] = '• ' . $s['name'];
            }
        } else {
            $lines[] = "Welcome to Aduanefie! I can help you discover fresh local farm produce, book ahead for crops currently farming, browse Backyard Grown harvests, or find cross-border wholesale exporters. Try asking for tomatoes, lettuce, maize, backyard farms, or wholesale quotes.";
        }

        return [
            'message' => implode("\n", $lines),
            'tool_name' => ! empty($formattedProducts) ? 'search_catalog' : null,
            'metadata' => [
                'stores' => $formattedStores,
                'products' => $formattedProducts,
                'categories' => $categories->toArray(),
                'cart_items' => [],
                'cart_updated' => false,
            ],
        ];
    }
}
