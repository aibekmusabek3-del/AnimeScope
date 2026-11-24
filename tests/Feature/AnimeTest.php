<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
class AnimeTest extends TestCase
{
    private function item(int $id=21): array {
        return ['id'=>$id,'name'=>'One Piece','russian'=>'Ван-Пис','score'=>'8.7','kind'=>'tv','status'=>'ongoing','aired_on'=>'1999-10-20','episodes'=>0,'episodes_aired'=>1000,'image'=>['original'=>'/poster.jpg'],'description'=>'[b]История[/b] пиратов.','genres'=>[['id'=>1,'name'=>'Action','russian'=>'Экшен']]];
    }
    protected function setUp(): void { parent::setUp(); config(['services.shikimori.retry_delay_ms'=>0]); Cache::flush(); Http::preventStrayRequests(); }
    public function test_search_and_filter(): void {
        Http::fake(['shikimori.one/*'=>Http::response([$this->item()])]);
        $this->getJson('/api/anime?q=Ван%20Пис&genre=1&type=tv&page=2&sort=title')->assertOk()->assertJsonPath('items.0.russian','Ван-Пис')->assertJsonPath('items.0.score',8.7);
        Http::assertSent(function($r){parse_str(parse_url($r->url(),PHP_URL_QUERY)??'', $q);return $q['search']==='Ван Пис' && $q['genre']==='1' && $q['kind']==='tv' && $q['page']==='2' && $q['order']==='name';});
    }
    public function test_detail(): void {Http::fake(['shikimori.one/*'=>Http::response($this->item())]);$this->getJson('/api/anime/21')->assertOk()->assertJsonPath('item.synopsis','История пиратов.')->assertJsonPath('item.image','https://shikimori.one/poster.jpg');}
    public function test_pagination(): void {
        Http::fake(function($r){parse_str(parse_url($r->url(),PHP_URL_QUERY)??'', $q);$start=($q['page']-1)*18+1;return Http::response(array_map(fn($id)=>$this->item($id),range($start,$start+17)));});
        foreach(['season'=>'ongoing','upcoming'=>'anons'] as $mode=>$status){
            $this->getJson('/api/anime?mode='.$mode.'&page=1')->assertOk()->assertJsonPath('items.0.id',1)->assertJsonPath('has_more',true);
            $this->getJson('/api/anime?mode='.$mode.'&page=2')->assertOk()->assertJsonPath('items.0.id',19);
            Http::assertSent(function($r)use($status){parse_str(parse_url($r->url(),PHP_URL_QUERY)??'', $q);return $q['status']===$status && $q['page']==='2';});
        }
    }
    public function test_cache_and_stale(): void {Http::fake(['shikimori.one/*'=>Http::sequence()->push([$this->item()])->push([],503)->push([],503)]);$this->getJson('/api/anime')->assertOk()->assertJsonPath('source','live');$this->getJson('/api/anime')->assertOk()->assertJsonPath('source','cache');$this->getJson('/api/anime?refresh=1')->assertOk()->assertJsonPath('source','stale');}
    public function test_rate_limit(): void {Http::fake(['shikimori.one/*'=>Http::sequence()->push([],429)->push([$this->item()])]);$this->getJson('/api/anime')->assertOk();Http::assertSentCount(2);}
    public function test_offline(): void {Http::fake(['shikimori.one/*'=>Http::response([],503)]);$this->getJson('/api/anime')->assertStatus(503);}
    public function test_not_found(): void {Http::fake(['shikimori.one/*'=>Http::response([],404)]);$this->getJson('/api/anime/21')->assertNotFound();}
    public function test_invalid_parameters(): void {$this->getJson('/api/anime?page=-1&genre=999')->assertUnprocessable();Http::assertNothingSent();}
    public function test_missing_rating(): void {$item=$this->item();unset($item['score']);Http::fake(['shikimori.one/*'=>Http::response($item)]);$this->getJson('/api/anime/21')->assertOk()->assertJsonPath('item.score',null);}
}
