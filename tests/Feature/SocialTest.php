<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SocialTest extends TestCase {
    use RefreshDatabase;
    private function account(string $name):int {return DB::table('watch_users')->insertGetId(['name'=>$name,'password'=>Hash::make('test-password'),'created_at'=>now(),'updated_at'=>now()]);}
    private function asUser(int $id):static {return $this->withSession(['watch_uid'=>$id]);}
    private function friends(int $a,int $b):void {DB::table('watch_friends')->insert(['low'=>min($a,$b),'high'=>max($a,$b),'sender'=>$a,'status'=>'accepted','created_at'=>now(),'updated_at'=>now()]);}
    public function test_registration_and_login():void {
        $this->postJson('/api/social/register',['name'=>'Alice','password'=>'test-password'])->assertOk()->assertJsonPath('user.name','alice')->assertJsonMissingPath('user.password');
        $this->assertTrue(Hash::check('test-password',DB::table('watch_users')->where('name','alice')->value('password')));
        $this->postJson('/api/social/logout')->assertOk();
        $this->postJson('/api/social/login',['name'=>'alice','password'=>'test-password'])->assertOk()->assertJsonPath('user.name','alice');
    }
    public function test_friend_request_requires_recipient():void {
        $a=$this->account('alice');$b=$this->account('bob');
        $this->asUser($a)->postJson('/api/social/friends',['name'=>'bob'])->assertOk();$id=DB::table('watch_friends')->value('id');
        $this->asUser($a)->postJson("/api/social/friends/$id/decision",['accept'=>true])->assertNotFound();
        $this->asUser($b)->getJson('/api/social/dashboard')->assertOk()->assertJsonPath('requests.0.incoming',true);
        $this->asUser($b)->postJson("/api/social/friends/$id/decision",['accept'=>true])->assertOk();
        $this->asUser($a)->getJson('/api/social/dashboard')->assertJsonPath('friends.0.name','bob');
    }
    public function test_room_invite_and_sync_permissions():void {
        $a=$this->account('alice');$b=$this->account('bob');$c=$this->account('carol');$this->friends($a,$b);
        $payload=['friend'=>$b,'title'=>'Example','video_url'=>'https://example.com/video.mp4'];
        $this->asUser($a)->postJson('/api/social/rooms',array_replace($payload,['friend'=>$c]))->assertForbidden();
        $id=$this->asUser($a)->postJson('/api/social/rooms',$payload)->assertCreated()->assertJsonPath('room.status','pending')->json('room.id');
        $this->asUser($c)->getJson("/api/social/rooms/$id")->assertNotFound();
        $this->asUser($a)->postJson("/api/social/rooms/$id/decision",['accept'=>true])->assertForbidden();
        $this->asUser($b)->getJson('/api/social/dashboard')->assertJsonPath('rooms.0.id',$id);
        $this->asUser($b)->postJson("/api/social/rooms/$id/decision",['accept'=>true])->assertOk()->assertJsonPath('room.status','active');
        $state=['position'=>42,'playing'=>false,'revision'=>1];
        $this->asUser($b)->postJson("/api/social/rooms/$id/control",$state)->assertForbidden();
        $this->asUser($a)->postJson("/api/social/rooms/$id/control",$state)->assertOk()->assertJsonPath('room.revision',2);
        $this->asUser($b)->getJson("/api/social/rooms/$id")->assertOk()->assertJsonPath('room.position',42);
        $this->asUser($a)->postJson("/api/social/rooms/$id/control",$state)->assertConflict();
        $this->asUser($b)->postJson("/api/social/rooms/$id/close")->assertOk();
        $this->asUser($a)->getJson("/api/social/rooms/$id")->assertJsonPath('room.status','closed');
    }
    public function test_guest_can_decline():void {
        $a=$this->account('alice');$b=$this->account('bob');$this->friends($a,$b);
        $id=$this->asUser($a)->postJson('/api/social/rooms',['friend'=>$b,'title'=>'Example','video_url'=>'https://example.com/video.mp4'])->assertCreated()->json('room.id');
        $this->asUser($b)->postJson("/api/social/rooms/$id/decision",['accept'=>false])->assertOk()->assertJsonPath('room.status','declined');
    }
    public function test_auth_required():void {$this->getJson('/api/social/dashboard')->assertUnauthorized();}
}
