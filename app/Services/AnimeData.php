<?php
namespace App\Services;

final class AnimeData
{
    // Legacy Shikimori genre IDs.
    public const GENRES = [1=>'Экшен',2=>'Приключения',4=>'Комедия',8=>'Драма',10=>'Фэнтези',14=>'Ужасы',7=>'Детектив',22=>'Романтика',24=>'Фантастика',36=>'Повседневность',37=>'Сверхъестественное',41=>'Триллер',30=>'Спорт'];
    public static function normalize(array $row): array
    {
        $image = $row['image']['original'] ?? $row['image']['preview'] ?? null;
        if (is_string($image) && str_starts_with($image, '/') && !str_starts_with($image, '//')) $image = 'https://shikimori.one'.$image;
        if (!is_string($image) || !str_starts_with($image, 'https://')) $image = null;
        $score = $row['score'] ?? null;
        $date = $row['aired_on'] ?? null;
        $description = $row['description'] ?? null;
        if (is_string($description)) {
            $description = preg_replace('/\[\/?[a-z_]+(?:=[^\]]*)?\]/iu', '', $description);
            $description = trim(html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $genres = [];
        foreach ($row['genres'] ?? [] as $g) {
            if (isset($g['id'])) $genres[] = ['id'=>$g['id'],'name'=>($g['russian'] ?? '') ?: ($g['name'] ?? '')];
        }
        return [
            'id'=>(int)$row['id'], 'title'=>$row['name'] ?? 'Без названия',
            'russian'=>$row['russian'] ?? null, 'english'=>$row['english'][0] ?? null, 'japanese'=>$row['japanese'][0] ?? null,
            'image'=>$image, 'score'=>is_numeric($score) && (float)$score > 0 && (float)$score <= 10 ? (float)$score : null,
            'year'=>is_string($date) && preg_match('/^\d{4}-/', $date) ? (int)substr($date,0,4) : null,
            'episodes'=>($row['episodes'] ?? 0) > 0 ? $row['episodes'] : ($row['episodes_aired'] ?? null),
            'type'=>match($row['kind'] ?? '') {'tv'=>'TV','movie'=>'Movie','ova'=>'OVA','ona'=>'ONA','special','tv_special'=>'Special',default=>$row['kind'] ?? null},
            'status'=>match($row['status'] ?? '') {'ongoing'=>'current','anons'=>'upcoming','released'=>'finished',default=>null},
            'genres'=>$genres, 'synopsis'=>$description ?: null,
            'studio'=>implode(', ', array_column($row['studios'] ?? [], 'name')) ?: null,
            'duration'=>!empty($row['duration']) ? $row['duration'].' мин.' : null,
            'url'=>'https://shikimori.one/animes/'.(int)$row['id'], 'members'=>null, 'sample'=>false,
        ];
    }
}
