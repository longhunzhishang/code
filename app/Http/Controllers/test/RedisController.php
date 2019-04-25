<?php

namespace App\Http\Controllers\test;

use Illuminate\Http\Request;

use App\Http\Controllers\TestController as Controller;
use Redis;
/**
 * finn
 * 20170706
 * home test api
 */
class RedisController extends Controller
{


	
	public function getRedis()
	{


//     	$d1 = '2016-07-26 00:00:00';
//     	$d2 = '2016-07-26 24:59:59';

//     	echo strtotime(date('YmdHis',time()));

//     	echo '<br/>';

//     	echo strtotime($d1);

//     	echo '<br/>';

//     	echo strtotime($d2);

//     	echo '<br/>';

//     	echo date('Ymd');
//     	echo '<br/>';



//     	$today = date('Ymd',time());

//     	$today_start = $today.' 00:00:00';
//     	$today_end = $today.' 24:59:59';

//     	echo 'today:';
//     	echo $today;
// echo '<br/>';
//     	echo 'today_start:';
//     	echo strtotime($today_start);
// echo '<br/>';
//     	echo 'today_end:';
//     	echo strtotime($today_end);
// echo '<br/>';
//     	$now_time = date('YmdHis',time());



// echo '<br/>';



		 $redis = new Redis();
    $redis->connect('127.0.0.1',6379);
//     // $redis->set('test','hello redis');
//     // echo $redis->get('test');
//     // 
//      $redis->lpush("tutorial-list", ['name'=>'a','time'=>'111111']);
//    $redis->lpush("tutorial-list", ['name'=>'v','time'=>'22222']);
//    $redis->lpush("tutorial-list", ['name'=>'c','time'=>'3333']);
//    // 获取存储的数据并输出
//    // $arList = $redis->lrange("tutorial-list", 0 ,5);
//    // echo "Stored string in redis";
//    // print_r($arList);
// $hset = array(

//  'name'=>'WUHAN SI',

//  'birth'=>1031,

//  'time'=>1111
// );


// $hset1 = array(

//  'name'=>'WUHAN SI11111',

//  'birth'=>103111,

//  'time'=>111111
// );


// $redis->hmset('tuntun',$hset);

// $redis->hmset('tuntun',$hset1);


// dump($redis->hgetall('tuntun'));


// echo '=====zadd=====';

// $redis->zadd('tuntung',1, 111);

// $redis->zadd('tuntung',2, 2222);

dump($redis->ZREVRANGE('tuntung',0,1,2));

// $data['time'] = 1;
// $data['name'] = 'c';
// $tiem = date('YmdH');
// $item_kyy = $tiem.'-'.'jihe';
// $redis->sAdd('2016072701',json_encode($data));

// $tiem = date('YmdH');
// $item_kyy = $tiem.'-'.'jihe';
// $redis->sAdd('2016072702',2);

// $tiem = date('YmdH');
// $item_kyy = $tiem.'-'.'jihe';
// $redis->sAdd('2016072703',3);

// dump($redis->SMEMBERS('20160727*'));


$redis->zAdd('name',1,111);

$redis->zAdd('name',2,2222);

$redis->zAdd('name',3,3333);

$redis->zAdd('name',4,4444);
$redis->zAdd('name',5,555);
$redis->zAdd('name',6,6666);

dump($redis->ZREVRANGE('name',1,5));

$time = date('YmdHis');

// 无序集合  key  score value
$redis->zAdd('name33',1,111);

$redis->zAdd('name33',2,2222);

$redis->zAdd('name33',3,3333);

$redis->zAdd('name33',4,4444);
$redis->zAdd('name33',5,555);

$redis->zAdd('name33',6,6666);

for ($i=10; $i <30 ; $i++) { 
	
	$redis->zAdd('name33',$i,$i);
}

dump($redis->ZREVRANGE('name33',0,4));

//查找范围值
dump($redis->ZRANGEBYSCORE('name33',4,20));
	}
  
}
