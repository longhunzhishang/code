<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;


class overdue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'overdue';

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
        $id = 0;
        while (DB::connection('mysql_oc2018')->table('users')->where('id', ">", $id)->where("vip_deadline", "!=",null)->limit(10)->count() > 0) {
            $rs = DB::connection('mysql_oc2018')->table('users')->where('id', ">", $id)->where("vip_deadline", "!=",null)->limit(10)->get();
            foreach ($rs as $key => $value) {
                if ($key == count($rs) - 1) {
                    $id = $value->id;
                }
                if (time() > strtotime($value->vip_deadline)) {
                    DB::connection('mysql_oc2018')->table('users')->where(['email' => $value->email])->update(["role" => 0,"character" => 0, "vip_start_time" => null, "vip_deadline" => null]);
                }

            }
        }
        echo "success";
    }
}
