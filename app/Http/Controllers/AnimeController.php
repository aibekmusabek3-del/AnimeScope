<?php
namespace App\Http\Controllers;

use App\Services\AnimeData;
use App\Services\ShikimoriClient;
use App\Services\ShikimoriException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AnimeController
{
    public function index(Request $request, ShikimoriClient $shiki)
    {
        $input = $request->validate([
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1|max:100',
            'mode' => ['nullable', Rule::in(['top', 'popular', 'season', 'upcoming', 'catalog'])],
            'genre' => ['nullable', 'integer', Rule::in(array_keys(AnimeData::GENRES))],
            'type' => ['nullable', Rule::in(['tv', 'movie', 'ova', 'ona', 'special'])],
            'sort' => ['nullable', Rule::in(['score', 'popular', 'newest', 'title'])],
            'refresh' => 'nullable|boolean',
        ]);
        $filters = [
            'q' => trim($input['q'] ?? ''), 'page' => (int) ($input['page'] ?? 1),
            'mode' => $input['mode'] ?? 'top', 'genre' => (int) ($input['genre'] ?? 0),
            'type' => $input['type'] ?? '', 'sort' => $input['sort'] ?? 'score',
        ];
        $query = ['limit'=>18, 'page'=>$filters['page'], 'censored'=>'true'];
        $path = 'animes';
        if ($filters['q'] !== '') $query['search'] = $filters['q'];
        if ($filters['genre']) $query['genre'] = $filters['genre'];
        if ($filters['type']) $query['kind'] = $filters['type'];
        if ($filters['mode'] === 'season') $query['status'] = 'ongoing';
        if ($filters['mode'] === 'upcoming') $query['status'] = 'anons';
        $query['order'] = match ($filters['mode'] === 'popular' ? 'popular' : $filters['sort']) {
            'popular'=>'popularity', 'newest'=>'aired_on', 'title'=>'name', default=>'ranked',
        };
        if (in_array($filters['mode'], ['season','upcoming'], true)) $query['order'] = 'id_desc';
        try {
            $result = $shiki->fetch($path, $query, $request->boolean('refresh'));
            $rows = $result['payload'];
            $items = array_values(array_map(fn ($a) => AnimeData::normalize($a), array_filter($rows, fn ($a) => is_array($a) && isset($a['id'], $a['name']) && ($a['rating'] ?? '') !== 'rx')));
            return response()->json([
                'items' => $items, 'has_more' => count($rows) === 18 && $filters['page'] < 100,
                'page' => $filters['page'], 'source' => $result['source'], 'fetched_at' => $result['fetched_at'], 'reason' => $result['reason'],
            ]);
        } catch (ShikimoriException $e) {
            return response()->json(['message'=>'Shikimori временно недоступен. Повторите запрос.', 'reason'=>$e->reason], 503);
        }
    }

    public function show(int $id, Request $request, ShikimoriClient $shiki)
    {
        if ($id < 1 || $id > 2147483647) return response()->json(['message' => 'Аниме не найдено.'], 404);
        $request->validate(['refresh' => 'nullable|boolean']);
        try {
            $result = $shiki->fetch('animes/'.$id, [], $request->boolean('refresh'));
            return response()->json(['item' => AnimeData::normalize($result['payload']), 'source' => $result['source'], 'fetched_at' => $result['fetched_at'], 'reason' => $result['reason']]);
        } catch (ShikimoriException $e) {
            if ($e->reason === 'not_found') return response()->json(['message' => 'Аниме не найдено.'], 404);
            return response()->json(['message'=>'Shikimori временно недоступен. Повторите запрос.', 'reason'=>$e->reason], 503);
        }
    }
}
