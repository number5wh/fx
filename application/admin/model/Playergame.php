<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/22
 * Time: 22:14
 */
namespace app\admin\model;
use think\Model;
use think\log;
class Playergame extends Model{



    /*
     * 获取每日玩家输赢
     */
    public function getFxUserGameWin($loginid, $mobile, $date){
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client = \Hprose\Client::create($ServiceUrl,false);
        try {
            $timestamp = getservertime();
            //print($timestamp);
            $appkey =config('appkey');
            $token =md5($appkey.$loginid.$mobile.$date.$timestamp);
            //$client->addFilter(new \Hprose\SizeFilter('Non compressed'))
            //   ->addFilter(new \Hprose\CompressFilter())
            //  ->addFilter(new \Hprose\SizeFilter('Compressed'));
            $ret = $client->getFxUserGameWin($loginid, $mobile, $date,$timestamp,$token);
            //save_log('sms', "mobile:{$mobile},data:{$ret->data},code:{$ret->code},msg:{$ret->message}");
            //Log::write($ret,'sms');
            return $ret;
        }catch (Exception $e) {
            return  false;
        }
    }

    public function getUsergame($proxyId)
    {
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client     = \Hprose\Client::create($ServiceUrl, false);
        try {
            $timestamp = getservertime();
            $appkey    = config('appkey');
            $token     = md5($appkey . $proxyId .  $timestamp);
            $ret       = $client->getUsergame($proxyId, $timestamp,  $token);
            //save_log('apidata/getUsergame', "proxyId:{$proxyId},code:{$ret->code}, message:{$ret->message}");
            return $ret;
        } catch (\Exception $e) {
            save_log('apidata/getUsergame', "proxyId:{$proxyId},code:500, message:{$e->getMessage()}");
            return (object)['code' => 500, 'message' => $e->getMessage()];
        }
    }

}