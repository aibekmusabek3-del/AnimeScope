<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('watch_users', function(Blueprint $t){$t->id();$t->string('name',32)->unique();$t->string('password');$t->timestamps();});
        Schema::create('watch_friends', function(Blueprint $t){$t->id();$t->unsignedBigInteger('low');$t->unsignedBigInteger('high');$t->unsignedBigInteger('sender');$t->string('status',12);$t->unique(['low','high']);$t->timestamps();});
        Schema::create('watch_rooms', function(Blueprint $t){$t->id();$t->unsignedBigInteger('host');$t->unsignedBigInteger('guest');$t->string('status',12);$t->string('title',150);$t->text('video_url');$t->double('position')->default(0);$t->boolean('playing')->default(false);$t->double('state_at');$t->unsignedInteger('revision')->default(0);$t->timestamps();$t->index(['guest','status']);$t->index(['host','status']);});
    }
    public function down(): void {Schema::dropIfExists('watch_rooms');Schema::dropIfExists('watch_friends');Schema::dropIfExists('watch_users');}
};
