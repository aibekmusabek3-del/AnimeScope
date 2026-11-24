import {test} from 'node:test';
import assert from 'node:assert/strict';
import {targetTime,shouldSeek} from '../../resources/js/watch-sync.js';
test('position bounded by video duration',()=>{assert.equal(targetTime(200,120),120);assert.equal(targetTime(-1,120),0);assert.equal(targetTime(30,120),30)});
test('correct drift without constantly seeking',()=>{assert.equal(shouldSeek(10,11),false);assert.equal(shouldSeek(10,12),true);assert.equal(shouldSeek(10,10.2,true),true)});
