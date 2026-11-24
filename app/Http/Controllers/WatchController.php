<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

final class WatchController
{
    private function uid(Request $r): int {
        $id=(int)$r->session()->get('watch_uid');
        abort_unless($id && DB::table('watch_users')->where('id',$id)->exists(),401,'Сначала войди в аккаунт.');
        return $id;
    }
    private function users(){return DB::table('watch_users')->select('id','name');}
    private function pair(int $a,int $b){return DB::table('watch_friends')->where('low',min($a,$b))->where('high',max($a,$b));}
    private function room(int $id,int $uid){$room=DB::table('watch_rooms')->where('id',$id)->where(fn($q)=>$q->where('host',$uid)->orWhere('guest',$uid))->first();abort_unless($room,404,'Комната не найдена.');return $room;}
    private function publicRoom($room): array {
        $position=(float)$room->position+($room->playing?max(0,microtime(true)-(float)$room->state_at):0);
        return ['id'=>$room->id,'host'=>$room->host,'guest'=>$room->guest,'status'=>$room->status,'title'=>$room->title,'video_url'=>$room->video_url,'position'=>$position,'playing'=>(bool)$room->playing,'revision'=>$room->revision,'host_name'=>$this->users()->where('id',$room->host)->value('name'),'guest_name'=>$this->users()->where('id',$room->guest)->value('name')];
    }
    private function sessionResponse(Request $r,$user){return response()->json(['user'=>$user,'csrf'=>$r->session()->token()]);}
    public function me(Request $r){return $this->sessionResponse($r,$this->users()->where('id',(int)$r->session()->get('watch_uid'))->first());}
    public function register(Request $r){
        $r->merge(['name'=>strtolower(trim((string)$r->input('name','')))]);
        $v=$r->validate(['name'=>['required','regex:/^[a-z0-9_]{3,32}$/','unique:watch_users,name'],'password'=>'required|string|min:8|max:72']);
        $id=DB::table('watch_users')->insertGetId(['name'=>$v['name'],'password'=>Hash::make($v['password']),'created_at'=>now(),'updated_at'=>now()]);
        $r->session()->regenerate();$r->session()->put('watch_uid',$id);
        return $this->sessionResponse($r,$this->users()->where('id',$id)->first());
    }
    public function login(Request $r){
        $v=$r->validate(['name'=>'required|string|max:32','password'=>'required|string|max:72']);
        $u=DB::table('watch_users')->where('name',strtolower(trim($v['name'])))->first();
        abort_unless($u && Hash::check($v['password'],$u->password),422,'Неверное имя пользователя или пароль.');
        $r->session()->regenerate();$r->session()->put('watch_uid',$u->id);
        return $this->sessionResponse($r,$this->users()->where('id',$u->id)->first());
    }
    public function logout(Request $r){$r->session()->invalidate();$r->session()->regenerateToken();return $this->sessionResponse($r,null);}
    public function dashboard(Request $r){
        $uid=$this->uid($r);$friends=[];$requests=[];
        foreach(DB::table('watch_friends')->where(fn($q)=>$q->where('low',$uid)->orWhere('high',$uid))->get() as $f){
            $other=$this->users()->where('id',$f->low==$uid?$f->high:$f->low)->first();if(!$other)continue;
            $entry=['id'=>$other->id,'name'=>$other->name,'request_id'=>$f->id,'incoming'=>$f->sender!=$uid];
            if($f->status==='accepted')$friends[]=$entry;else $requests[]=$entry;
        }
        $rooms=DB::table('watch_rooms')->where(fn($q)=>$q->where('host',$uid)->orWhere('guest',$uid))->whereIn('status',['pending','active'])->orderByDesc('id')->limit(50)->get()->map(fn($r)=>$this->publicRoom($r));
        return response()->json(compact('friends','requests','rooms'));
    }
    public function add(Request $r){
        $uid=$this->uid($r);$v=$r->validate(['name'=>'required|string|max:32']);$other=$this->users()->where('name',strtolower(trim($v['name'])))->first();
        abort_unless($other,404,'Пользователь не найден.');abort_if($other->id==$uid,422,'Нельзя добавить себя.');
        $existing=$this->pair($uid,$other->id)->first();abort_if($existing,409,'Запрос уже отправлен или вы уже друзья.');
        DB::table('watch_friends')->insertOrIgnore(['low'=>min($uid,$other->id),'high'=>max($uid,$other->id),'sender'=>$uid,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);return response()->json(['ok'=>true]);
    }
    public function friendDecision(Request $r,int $id){
        $uid=$this->uid($r);$v=$r->validate(['accept'=>'required|boolean']);
        $q=DB::table('watch_friends')->where('id',$id)->where(fn($q)=>$q->where('low',$uid)->orWhere('high',$uid))->where('sender','!=',$uid)->where('status','pending');
        $n=$v['accept']?$q->update(['status'=>'accepted','updated_at'=>now()]):$q->delete();abort_unless($n,404,'Запрос не найден.');return response()->json(['ok'=>true]);
    }
    public function invite(Request $r){
        $uid=$this->uid($r);$v=$r->validate(['friend'=>'required|integer','title'=>'required|string|max:150','video_url'=>['required','string','max:2048','url:https']]);
        abort_unless($this->pair($uid,(int)$v['friend'])->where('status','accepted')->exists(),403,'Можно приглашать только друзей.');
        $parts=parse_url($v['video_url']);abort_if(isset($parts['user'])||isset($parts['pass']),422,'Ссылка не должна содержать логин или пароль.');
        $id=DB::table('watch_rooms')->insertGetId(['host'=>$uid,'guest'=>$v['friend'],'status'=>'pending','title'=>$v['title'],'video_url'=>$v['video_url'],'position'=>0,'playing'=>false,'state_at'=>microtime(true),'revision'=>0,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['room'=>$this->publicRoom($this->room($id,$uid))],201);
    }
    public function decision(Request $r,int $id){
        $uid=$this->uid($r);$v=$r->validate(['accept'=>'required|boolean']);$room=$this->room($id,$uid);abort_unless($room->guest==$uid,403,'Принять приглашение может только получатель.');
        $n=DB::table('watch_rooms')->where('id',$id)->where('status','pending')->update(['status'=>$v['accept']?'active':'declined','revision'=>DB::raw('revision + 1'),'updated_at'=>now()]);abort_unless($n,409,'Приглашение уже обработано.');return response()->json(['room'=>$this->publicRoom($this->room($id,$uid))]);
    }
    public function state(Request $r,int $id){return response()->json(['room'=>$this->publicRoom($this->room($id,$this->uid($r)))]);}
    public function control(Request $r,int $id){
        $uid=$this->uid($r);$room=$this->room($id,$uid);abort_unless($room->host==$uid,403,'Управлять воспроизведением может создатель комнаты.');
        $v=$r->validate(['position'=>'required|numeric|min:0|max:86400','playing'=>'required|boolean','revision'=>'required|integer|min:0']);
        $n=DB::table('watch_rooms')->where('id',$id)->where('status','active')->where('revision',$v['revision'])->update(['position'=>$v['position'],'playing'=>$v['playing'],'state_at'=>microtime(true),'revision'=>DB::raw('revision + 1'),'updated_at'=>now()]);
        abort_unless($n,409,'Состояние изменилось. Повтори действие после обновления.');return response()->json(['room'=>$this->publicRoom($this->room($id,$uid))]);
    }
    public function close(Request $r,int $id){$uid=$this->uid($r);$this->room($id,$uid);DB::table('watch_rooms')->where('id',$id)->whereIn('status',['pending','active'])->update(['status'=>'closed','playing'=>false,'revision'=>DB::raw('revision + 1'),'updated_at'=>now()]);return response()->json(['ok'=>true]);}
}
