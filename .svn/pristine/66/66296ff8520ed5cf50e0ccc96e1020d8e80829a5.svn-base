<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/22
 * Time: 22:14
 */
namespace app\manage\model;
use think\Log;
use think\Model;
class Distribute extends Model{

    public function setProxyId($proxyId, $userId)
    {
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client = \Hprose\Client::create($ServiceUrl,false);
        try {
            $timestamp =getservertime();
            $appkey =config('appkey');
            $token =md5($appkey.$proxyId.$userId.$timestamp);
            $ret = $client->UpdateUserProxyId($proxyId,$userId, $timestamp,$token);
            return  $ret;
        }catch (\Exception $e) {
            Log::write($e->getMessage(),'debug');
            return  null;
        }
    }
}