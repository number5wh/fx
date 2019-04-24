<?php
namespace app\admin\model;
use think\Model;
use think\log;

class Company extends Model{

public function getPlayer($proxy_id){
    vendor('Hprose.Hprose');
    $client = \Hprose\Client::create('http://apiservice.huofenghuang999.com/service.php',false);
    try {
        $timestamp =getservertime();
        $appkey =config('appkey');
        $token =md5($appkey.$proxy_id.$timestamp);
        $client->addFilter(new \Hprose\SizeFilter('Non compressed'))
            ->addFilter(new \Hprose\CompressFilter())
            ->addFilter(new \Hprose\SizeFilter('Compressed'));
        $ret = $client->getList($proxy_id,$timestamp,$token);
        //Log::write($ret,'debug');
        return  json_decode($ret,false);
    }catch (Exception $e) {
        return  null;
    }
}


public function getBillList($proxy_id,$curPage,$pagesize){
    vendor('Hprose.Hprose');
    $client = \Hprose\Client::create('http://apiservice.huofenghuang999.com/service.php',false);
    try {
        $timestamp =getservertime();
        $appkey =config('appkey');
        $token =md5($appkey.$proxy_id.$timestamp);
        $client->addFilter(new \Hprose\SizeFilter('Non compressed'))
            ->addFilter(new \Hprose\CompressFilter())
            ->addFilter(new \Hprose\SizeFilter('Compressed'));
        $Time = date('Ymd');
        $ret = $client->getBilllist($proxy_id,$timestamp,$token,$Time,$curPage,$pagesize,new \Hprose\InvokeSettings(array('timeout'=>30000)));
        //Log::write($ret,'debug');
        return  json_decode($ret,false);
    }catch (Exception $e) {
        return  null;
    }


}


}