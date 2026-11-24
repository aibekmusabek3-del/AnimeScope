import test from 'node:test';
import assert from 'node:assert/strict';
import {titleOf,scoreOf,listUrl,toggleFavorite,loadFavorites,readDetail} from '../../resources/js/catalog.js';
test('title fallback',()=>{assert.equal(titleOf({title:'Original',english:'English',russian:'Русский'}),'Русский');assert.equal(titleOf({title:'Original'}),'Original')});
test('scores never invented',()=>{assert.equal(scoreOf({score:null}),null);assert.equal(scoreOf({score:8.24}),'8.2');assert.equal(scoreOf({score:0}),null)});
test('search encoding and refresh',()=>{const url=new URL(listUrl({q:'A & B',genre:'',page:2},true),'http://localhost');assert.equal(url.searchParams.get('q'),'A & B');assert.equal(url.searchParams.get('refresh'),'1');assert.equal(url.searchParams.has('genre'),false)});
test('collection add and remove',()=>{const a={id:20,title:'Naruto'};assert.deepEqual(toggleFavorite([],a),[a]);assert.deepEqual(toggleFavorite([a],a),[])});
test('corrupt local storage',()=>{assert.deepEqual(loadFavorites({getItem:()=>'{broken'}),[]);assert.deepEqual(loadFavorites({getItem:()=>JSON.stringify([{id:20},null,{}])}),[{id:20}])});
test('detail route',()=>{assert.equal(readDetail('#/anime/20'),20);assert.equal(readDetail('#/anime/0'),null);assert.equal(readDetail('#/bad'),null)});
