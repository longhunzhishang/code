<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Utils\HttpUtil;
use DB;
use \Exception;

class cleartime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleartime';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '用户搜索次数等其他数据定期清零';

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
        try {
            DB::connection('mysql_oc2018')->table('users')->update(["search_times" => 0, "downloads" => 0, "landing" => 0]);

        } catch (Exception $e) {
            $data["shell_name"] = "用户搜索次数等数据定时清零";
            $data["falut_infor"] = $e->getMessage();
            $data["created_time"] = date("Y-m-d H:i:s", time());
            DB::connection('web_running_data')->table('shell_run_log')->insert($data);
        }
        return HttpUtil::retrun_json(["message" => "成功"], 200);
    }
}
