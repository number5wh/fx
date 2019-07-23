<?php

namespace app\manage\model;

use think\Model;
use think\log;

class Company extends Model
{

    public function updatefxplayerstatus($id, $classid)
    {
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client     = \Hprose\Client::create($ServiceUrl, false);
        try {
            $timestamp = getservertime();
            $appkey    = config('appkey');
            $timestamp = $timestamp->data;
            $token     = md5($appkey . $id.$classid . $timestamp);
            $ret = $client->updatefxplayerstatus($id, $classid, $timestamp, $token);
            return $ret;
        } catch (Exception $e) {
            Log::write($e->getMessage(), 'debug');
            return null;
        }
    }

    public function updatefxalluserstatus($id)
    {
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client     = \Hprose\Client::create($ServiceUrl, false);
        try {
            $timestamp = getservertime();
            $appkey    = config('appkey');
            $timestamp = $timestamp->data;
            $token     = md5($appkey . $id . $timestamp);
            $ret = $client->updatefxalluserstatus($id,  $timestamp, $token);
            return $ret;
        } catch (Exception $e) {
            Log::write($e->getMessage(), 'debug');
            return null;
        }
    }


    public function updateProxyBillStatus($id, $classid)
    {
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client     = \Hprose\Client::create($ServiceUrl, false);
        try {
            $timestamp = getservertime();
            $appkey    = config('appkey');
            $timestamp = $timestamp->data;
            $token     = md5($appkey . $id.$classid . $timestamp);
            $ret = $client->updateproxystatus($id, $classid, $timestamp, $token);
            return $ret;
        } catch (Exception $e) {
            Log::write($e->getMessage(), 'debug');
            return null;
        }
    }

    public function getPlayer()
    {
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client     = \Hprose\Client::create($ServiceUrl, false);
        try {
            $timestamp = getservertime();
            $appkey    = config('appkey');
            $timestamp = $timestamp->data;
            $token     = md5($appkey  . $timestamp);
            //$client->addFilter(new \Hprose\SizeFilter('Non compressed'))
            //    ->addFilter(new \Hprose\CompressFilter())
            //    ->addFilter(new \Hprose\SizeFilter('Compressed'));
            $ret = $client->getFxPlayerList($timestamp, $token);
            //
            return $ret;
        } catch (Exception $e) {
            Log::write($e->getMessage(), 'debug');
            return null;
        }
    }

    public function getBillList()
    {
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client     = \Hprose\Client::create($ServiceUrl, false);
        try {
            $timestamp = getservertime();

            $appkey    = config('appkey');
            $timestamp = $timestamp->data;
            $token     = md5($appkey . $timestamp);
            //$client->addFilter(new \Hprose\SizeFilter('Non compressed'))
            //    ->addFilter(new \Hprose\CompressFilter())
            //    ->addFilter(new \Hprose\SizeFilter('Compressed'));
            $Time = date('Ymd');
            $ret  = $client->getFxBillList($timestamp, $token, new \Hprose\InvokeSettings(array('timeout' => 360000)));
            //Log::write($ret,'debug');
            return $ret;
        } catch (Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }


    /*
     *   获取充值记录
     */
    public function getPayTime()
    {
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client     = \Hprose\Client::create($ServiceUrl, false);
        try {
            $timestamp = getservertime();
            $appkey    = config('appkey');
            $timestamp = $timestamp->data;
            $Time      = date('Ymd');
            $token     = md5($appkey . $Time . $timestamp);
            $ret       = $client->getFxRechargeInfo($Time, $timestamp, $token, new \Hprose\InvokeSettings(array('timeout' => 360000)));
            //Log::write($ret,'debug');
            return $ret;
        } catch (Exception $e) {
            return null;
        }
    }

    //获取所有玩家数据
    public function getAllPlayerList()
    {
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client     = \Hprose\Client::create($ServiceUrl, false);
        try {
            $timestamp = getservertime();
            $appkey    = config('appkey');
            $timestamp = $timestamp->data;
            $token     = md5($appkey . $timestamp);
            $Time      = date('Ymd');
            $ret       = $client->getFxAllPlayerList($timestamp, $token, new \Hprose\InvokeSettings(array('timeout' => 360000)));
            return $ret;
        } catch (Exception $e) {
            return null;
        }
    }


}