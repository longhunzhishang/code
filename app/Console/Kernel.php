<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Utils\ESUtil;
use App\Http\Utils\HttpUtil;
use Search;
USE App\Http\Utils\DBUtil;
use Elasticsearch\Client;
use DB;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Commands\Inspire::class,
        \App\Console\Commands\MailPush::class,
  //      \App\Console\Commands\pushSubscribe::class,
        \App\Console\Commands\MemberDataOperation::class,
        \App\Console\Commands\test::class,
        \App\Console\Commands\overdue::class,
        \App\Console\Commands\dataAdd::class,
        \App\Console\Commands\pushSubscribeFile::class,
        \App\Console\Commands\pushSubscribeAd::class,
        \App\Console\Commands\reservationNotice::class,
         \App\Console\Commands\cleartime::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
       // $schedule->command('pushSubscribe')->dailyAt('11:00');
         $schedule->command('cleartime')->dailyAt('1:00');
        $schedule->command('MemberDataOperation')->dailyAt('15:00');
        $schedule->command('pushSubscribeFile')->dailyAt('5:00');
        $schedule->command('pushSubscribeAd')->dailyAt('16:00');
        $schedule->command('overdue')->everyThirtyMinutes();
       // $schedule->command('reservationNotice')->everyTenMinutes();


        // $schedule->call(function () {
        //             $db = DB::table('maxuser')->where('id',1)->first();
        //             $id = 0;
        //             if($db)$id = $db->uid;




        //             $userinfo = DB::table('oc_ucenter_member')
        //             ->join('oc_member','oc_member.uid','=','oc_ucenter_member.id')
        //             ->select('oc_ucenter_member.email','oc_member.nickname')
        //             ->where('oc_ucenter_member.id','>',$id)
        //             ->orderBy('id','desc')
        //             ->get();

        //             if($userinfo->count())
        //             {

        //                 foreach ($userinfo as $key => $value) {
        //                     # code...
        //                 }
        //                 DB::table('users')
        //                 ->where('id', 1)
        //                 ->update(['uid' => ]);
        //             }
        //         })->everyMinute();


        // $curl_param = [
        //     'aggs'=>[
        //         'max_id'=>[
        //             'max'=>[
        //                 'field'=>'id'
        //             ]
        //         ]
        //     ]
        // ];
        // $url = config('param.ES_URL').'subject/subject_index/'.config('param.ES_SEARCH').'?_source=id';
        // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        // $es = json_decode($rs,true);
        // $max_id = 0;

        // if(!empty($es['aggregations']['max_id']['value']))
        // {
        //     $max_id = intval($es['aggregations']['max_id']['value']);
        //     $schedule->exec('/home/elasticsearch-jdbc-2.3.2.0/bin/import_subject.sh '.$max_id)->daily();
        // }



        // $url = config('param.ES_URL').'hostmeda/hostmeda_index/'.config('param.ES_SEARCH').'?_source=id';
        // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        // $es = json_decode($rs,true);
        // if(!empty($es['aggregations']['max_id']['value']))
        // {
        //     $max_id = intval($es['aggregations']['max_id']['value']);
        //     $schedule->exec('/home/elasticsearch-jdbc-2.3.2.0/bin/import_hostmeda.sh '.$max_id)->daily()->withoutOverlapping();
        // }
        

        // $url = config('param.ES_URL').'mains/main_index/'.config('param.ES_SEARCH');
        // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        // $es = json_decode($rs,true);
        // if(!empty($es['aggregations']['max_id']['value']))
        // {
        //     $max_id = intval($es['aggregations']['max_id']['value']);
        //     $schedule->command('/home/elasticsearch-jdbc-2.3.2.0/bin/import_mains_index.sh '.$max_id)->everyMinute();
        // }

        // $url = config('param.ES_URL').'domain/domain_index/'.config('param.ES_SEARCH');
        // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        // $es = json_decode($rs,true);
        // if(!empty($es['aggregations']['max_id']['value']))
        // {
        //     $max_id = intval($es['aggregations']['max_id']['value']);
        //     file_put_contents('/home/domain.txt', $max_id);
        //     $schedule->command('/home/elasticsearch-jdbc-2.3.2.0/bin/import_domains.sh '.$max_id)->everyMinute();
        // }else{
        //     file_put_contents('/home/domain.txt', 'no max_id domain');
        // }


        // $url = config('param.ES_URL').'addata/addata_index/'.config('param.ES_SEARCH');
        // $rs = HttpUtil::send_curl_methods($url,$curl_param,"POST");

        // $es = json_decode($rs,true);
        // if(!empty($es['aggregations']['max_id']['value']))
        // {
        //     $max_id = intval($es['aggregations']['max_id']['value']);
        //     $schedule->exec('/home/elasticsearch-jdbc-2.3.2.0/bin/import_addata.sh '.$max_id)->everyMinute()->withoutOverlapping();
        // }
        

        // $schedule->command('inspire')
        //          ->hourly();
    }
}
