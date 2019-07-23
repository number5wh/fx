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
class Sms extends Model{



    public function checksend($mobile,$event,$ipaddr){
        if (!$mobile || !\think\Validate::regex($mobile, "^1\d{10}$")) {
            return 100;
        }

        $SmsModel =Model("Sms");
        $where = array("mobile"=>$mobile,"event"=>$event);
        $data = $SmsModel->where($where)->order('id desc')->limit(1)->select();
        if(!empty($data)){
            if(time() - $data[0]['addtime'] < 120)
            {
                return 101;
            }
        }

        $ipSendTotal = $SmsModel->where(['ipaddr' => $ipaddr])
            ->whereTime('addtime', '-1 hours')->count();

        if($ipSendTotal>=5){
            return 102;
        }

        return  0;
    }



    public function sendcode($mobile,$code,$event,$ipaddr){
        if(empty($mobile) || empty($code)){
            return true;
        }

        $data =  array("mobile"=>$mobile,"code"=>$code,"event"=>$event,
            "addtime"=>time(),"ipaddr"=>$ipaddr);
        $SmsModel = Model("Sms");
        $status = $SmsModel->insert($data);
        return $status >0;
    }



    /*
     * 检查及发送
     */
    public function checkandsend($mobile,$code,$event,$ipaddr){
        if($this->checksend($mobile,$event,$ipaddr)!=0){
            return false;
        }
        $status = $this->sendcode($mobile,$code,$event,$ipaddr);
        return $status >0;
    }


    /*
     * 核对验证码是否正确
     */
    public function checkcode($mobile,$code,$event){
        $SmsModel = Model("Sms");
        $where = array('mobile'=>$mobile,'code'=>$code,'event'=>$event);
        $data = $SmsModel->where($where)->find();

        if($data && time()-$data['addtime']<=60*30){
            return true;
        }
        else
        {
            return false;
        }

    }


    /*
     * 发送短信
     */
    public function send_sms($mobile){
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client = \Hprose\Client::create($ServiceUrl,false);
        try {
            $timestamp = getservertime();
            $timestamp = $timestamp->data;
			//print($timestamp);
            $appkey =config('appkey');
            $token =md5($appkey.$mobile.$timestamp);
            //$client->addFilter(new \Hprose\SizeFilter('Non compressed'))
             //   ->addFilter(new \Hprose\CompressFilter())
              //  ->addFilter(new \Hprose\SizeFilter('Compressed'));
            $ret = $client->sendCode($mobile,$timestamp,get_ip(),$token);
            save_log('sms', "mobile:{$mobile},data:{$ret->data},code:{$ret->code},msg:{$ret->message}");
            //Log::write($ret,'sms');
            return $ret;
        }catch (Exception $e) {
            return  false;
        }
    }


    /*
     * 检测验证码
     */
    public function validateSms($mobile,$code){
        vendor('Hprose.Hprose');
        $ServiceUrl = config("ServiceUrl");
        $client = \Hprose\Client::create($ServiceUrl,false);
        try {
            $timestamp =getservertime();
            $appkey =config('appkey');
            $timestamp = $timestamp->data;
            $token =md5($appkey.$mobile.$code.$timestamp);
            //$client->addFilter(new \Hprose\SizeFilter('Non compressed'))
             //   ->addFilter(new \Hprose\CompressFilter())
             //   ->addFilter(new \Hprose\SizeFilter('Compressed'));
            $ret = $client->validateCode($mobile,$code,$timestamp,$token);
			//print($ret->code);exit();
			//print_r($ret);exit();
            //Log::write($ret,'debug');
            return  $ret;
        }catch (Exception $e) {
            return  false;
        }
    }


}