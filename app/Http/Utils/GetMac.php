<?php

namespace App\Http\Utils;
use Illuminate\Support\Facades\Auth;
use Search;
use Elasticsearch\Client;

/**
* zhujh 获取客户端标识类
* 20160624
*/
class GetMac{
    var $result   = array();
    var $macAddrs = array(); //所有mac地址
    var $macAddr;            //第一个mac地址

    function __construct($OS){
        $this->GetMac($OS);
    }

    function GetMac($OS){
        switch ( strtolower($OS) ){
            case "unix": break;
            case "solaris": break;
            case "aix": break;
            case "linux":
                $this->getLinux();
                break;
            default:
                $this->getWindows();
                break;
        }
        $tem = array();
        foreach($this->result as $val){
            if(preg_match("/[0-9a-f][0-9a-f][:-]"."[0-9a-f][0-9a-f][:-]"."[0-9a-f][0-9a-f][:-]"."[0-9a-f][0-9a-f][:-]"."[0-9a-f][0-9a-f][:-]"."[0-9a-f][0-9a-f]/i",$val,$tem) ){
                $this->macAddr = $tem[0];//多个网卡时，会返回第一个网卡的mac地址，一般够用。
                break;
                //$this->macAddrs[] = $temp_array[0];//返回所有的mac地址
            }
        }
        unset($temp_array);
        return $this->macAddr;
    }
    //Linux系统
    function getLinux(){
        @exec("ifconfig -a", $this->result);
        return $this->result;
    }
    //Windows系统
    function getWindows(){
        @exec("ipconfig /all", $this->result);
        if ( $this->result ) {
            return $this->result;
        } else {
            $ipconfig = $_SERVER["WINDIR"]."\system32\ipconfig.exe";
            if(is_file($ipconfig)) {
                @exec($ipconfig." /all", $this->result);
            } else {
                @exec($_SERVER["WINDIR"]."\system\ipconfig.exe /all", $this->result);
                return $this->result;
            }
        }
    }
    function  getRemoteMac(){
        // @exec("arp -a",$array);
        // foreach($array as $value){
        //     if(strpos($value,$_SERVER["REMOTE_ADDR"]) && preg_match("/(:?[0-9A-F]{2}[:-]){5}[0-9A-F]{2}/i",$value,$mac_array)){
        //         $mac = $mac_array[0];
        //         break;
        //     }
        // }
        // return $mac;
         @exec("ipconfig /all",$array);
 for($Tmpa;$Tmpa<count($array);$Tmpa++){
  if(eregi("Physical",$array[$Tmpa])){
   $mac=explode(":",$array[$Tmpa]);
   return $mac[1];
  }
 }
    }
}

