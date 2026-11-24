<?php
use App\Services\ShikimoriClient;
use App\Services\ShikimoriException;
use Illuminate\Support\Facades\Artisan;
Artisan::command('anime:check', function () {
    try {
        $api = app(ShikimoriClient::class);
        $detail = $api->request('animes/21');
        $this->info('LIVE API OK: '.($detail['russian'] ?: $detail['name']));
        foreach (['ongoing','anons'] as $status) {
            $pages = [];
            foreach ([1,2] as $page) {
                usleep(600000);
                $rows = $api->request('animes',['status'=>$status,'page'=>$page,'limit'=>18,'order'=>'id_desc','censored'=>'true']);
                $pages[] = array_column($rows,'id');
            }
            if (!$pages[0] || !$pages[1] || array_intersect($pages[0],$pages[1])) { $this->error('Pagination check failed: '.$status); return 1; }
            $this->info('PAGINATION OK: '.$status);
        }
        return 0;
    } catch (ShikimoriException $e) { $this->error('API FAILED: '.$e->reason.' / '.$e->getMessage()); return 1; }
})->purpose('Check Shikimori connectivity and pagination');
