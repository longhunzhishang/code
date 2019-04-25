<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api9\ApiController;
use App\Http\Utils\Tools;
use Illuminate\Http\Request;
use App\Http\Utils\EsSelect;
use App\Http\Utils\DBUtil;
use App\Http\Utils\HttpUtil;
use App\Http\Utils\ESUtil;
use App\Http\Utils\DSLUtil;
use DB;

class reservationNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservationNotice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $rs =DB::connection('mysql_oc2018')->table('reservation_infor')->where("status",0)->get();
        if(empty($rs))return false;
        $data1 = [];
        $data2 = [];
        foreach ( $rs as $key =>$value){
            if($value->product == 1){
              array_push($data1,["user_name"=>$value->user_name,"email"=>$value->email,"telphone"=>$value->telphone,"film_name"=>$value->film_name,"job"=>$value->job]);
            }else{
               array_push($data2,["user_name"=>$value->user_name,"email"=>$value->email,"telphone"=>$value->telphone,"film_name"=>$value->film_name,"job"=>$value->job]);
            }
           
        }
        DB::connection('mysql_oc2018')->table('reservation_infor')->update(["status"=>1]);
         $data['subject'] = "!!有新的销售线索";
         $data["view"] = "reservanotice";
         $data['email'] = "sam@adbug.cn";
         $data['data'] = $data1;
         Tools::sendMailWarn($data);
         $data['email'] = "katelu@adbug.cn";
         $data['data'] = $data2;
         Tools::sendMailWarn($data);
        //
    }
}
