<?php

namespace app\manage\controller;

use app\manage\controller\Base;
use redis\Redis;
use think\Db;
use think\captcha\Captcha;
use app\manage\model;


class Index extends Base
{

    public function index()
    {
        if ($this->verify()) {
            $proxy = session("manage");

            $roleid = session("RoleId");

            $this->assign("roleid", $roleid);
            $this->assign("proxy", $proxy);
            return $this->fetch();
        } else {
            $this->redirect('login/index');
        }
    }

    public function main()
    {
        $message = Db::name("message")->order('id desc ')->select();
        $today   = strtotime(date('Y-m-d 00:00:00'));

        // $now =
        $data['addtime'] = array('egt', $today);
        $proxy_id        = 1;

        $regData = Redis::getInstance()->get($proxy_id . __METHOD__ . 'current' . date('Ymd'));
        if (!$regData) {
            $regtotal = Db::name("spreadsum")->sum('regnum');
            $regtoday = Db::name("spreadsum")->where(['day' => date('Ymd')])->sum('regnum');
            $regyestoday = Db::name("spreadsum")->where(['day' => date('Ymd') - 1])->sum('regnum');
            $regData  = [
                'regtotal' => $regtotal,
                'regtoday' => $regtoday,
                'regyestoday' => $regyestoday,
            ];
            Redis::getInstance()->set($proxy_id . __METHOD__ . 'current' . date('Ymd'), $regData, rand(300, 600));
        }


        $sumdata = Redis::getInstance()->get($proxy_id . __METHOD__ . 'historyin' . date('Ymd'));

        if (!$sumdata) {

            $sumdata = Db::name("sumdata")
                ->where([
                    'proxy_id' => $proxy_id
                ])->find();
            if (!$sumdata) {
                $sumdata = [
                    //总
                    'totalin'         => 0,
                    'historytax'      => 0,
                    //直营
                    "totaltax"        => 0,
                    "totalchangmoney" => 0,
                    //下级代理团队
                    "teamtax"         => 0,
                    "teamchangmoney"  => 0,
                ];
            }
            Redis::getInstance()->set($proxy_id . __METHOD__ . 'historyin' . date('Ymd'), $sumdata, rand(300, 600));
        }

        $today = date('Ymd');
        //今日团队税收 && 今日团队收益 && 今日直营税收 && 今日直营收益
        $alltodaydata = Redis::getInstance()->get($proxy_id . __METHOD__ . 'alltodaydata' . date('Ymd'));
        if (!$alltodaydata) {
            $alltodaydata = Db::name("spreadsum")
                ->where([
                    'proxy_id' =>$proxy_id,
                    'day'      => $today,
                ])->find();
            if (!$alltodaydata) {
                $alltodaydata = [
                    "totalin"         => 0,
                    "historytax"         => 0,
                    //直营
                    "totaltax"        => 0,
                    "totalchangmoney" => 0,
                    //下级代理团队
                    "teamtax"         => 0,
                    "teamchangmoney"  => 0,
                ];
            }
            Redis::getInstance()->set($proxy_id . __METHOD__ . 'alltodaydata' . date('Ymd'), $alltodaydata, rand(300, 600));
        }


        //今日团队税收 && 今日团队收益 && 今日直营税收 && 今日直营收益
        $yestodaydata = Redis::getInstance()->get($proxy_id . __METHOD__ . 'yestodaydata' . date('Ymd'));
        if (!$yestodaydata) {
            $yestodaydata = Db::name("spreadsum")
                ->where([
                    'proxy_id' => $proxy_id,
                    'day'      => $today-1,
                ])->find();
            if (!$yestodaydata) {
                $yestodaydata = [
                    "totalin"         => 0,
                    "historytax"         => 0,
                    //直营
                    "totaltax"        => 0,
                    "totalchangmoney" => 0,
                    //下级代理团队
                    "teamtax"         => 0,
                    "teamchangmoney"  => 0,
                ];
            }
            Redis::getInstance()->set($proxy_id . __METHOD__ . 'yestodaydata' . date('Ymd'), $yestodaydata, rand(300, 600));
        }


        //今日提现 总提现  总余额
        $todaywithdraw = Db::name('checklog')->where(['addtime' => ['like', date('Y-m-d').'%'], 'status' => ['in', [4,5]]])->sum('amount');
        $totalwithdraw = Db::name('checklog')->where(['status' => ['in', [4,5]]])->sum('amount');
        $totalmoney = Db::name('proxy')->where(['islock' => 0, 'code' => ['neq', '1']])->sum('balance');





        $this->assign('regtotal', $regData['regtotal']);
        $this->assign('regtoday', $regData['regtoday']);
        $this->assign("regzt", $regData['regyestoday']);

        $this->assign("totaltax", $sumdata['historytax']);
        $this->assign("taxyestoday", $yestodaydata['historytax']);
        $this->assign("taxtoday", $alltodaydata['historytax']);

        $this->assign("todaywithdraw", $todaywithdraw);
        $this->assign("totalwithdraw", $totalwithdraw);
        $this->assign("totalmoney", $totalmoney);
        $this->assign("list", $message);
        return $this->fetch();
    }

    public function notice()
    {
        return $this->fetch();
    }


    public function sysadmin()
    {

        $list = Db::name("sysadmin")->paginate();

        $this->assign("list", $list);
        return $this->fetch();
    }


    public function addadmin()
    {
        $username   = input("usernameForAdd");
        $password   = input("passwordForAdd");
        $repassword = input("repasswordForAdd");
        $realname   = input("realnameForAdd");
        $roleid     = input("roleid");

        if (empty($username) || empty($password) || empty($realname)) {
            return $this->msg(0, "参数错误", null);
        }

        if ($password !== $repassword) {
            return $this->msg(0, "两次输入密码不一致", null);
        }

        if ($roleid == '') {
            return $this->msg(0, "请选择角色", null);
        }


        $num = Db::name("sysadmin")->where("username", $username)->count();
        if ($num > 0) {
            return $this->msg(0, "管理员已经存在", null);
        }
        $salt     = strtolower(generateSalt());
        $password = md5($salt . $password);
        $data     = array("username" => $username, "password" => $password, "salt" => $salt, "realname" => $realname, "isdel" => 0, "roleid" => $roleid);

        $result = Db::name("sysadmin")->insertGetId($data);
        if ($result > 0) {
            return $this->msg(1, "管理员添加成功", null);
        } else {
            return $this->msg(0, "添加失败，请重试", null);
        }
    }


    public function deladmin()
    {
        $id = input("id");
        if (!is_numeric($id)) {
            return $this->msg(0, "参数错误", null);
        }

        $roleid = Db::name("sysadmin")->where("id", $id)->value("roleid");

        if ($roleid == 1) {
            return $this->msg(0, "超级管理员无法删除", null);
        }

        $result = Db::name("sysadmin")->where(["id" => $id, "roleid" => 0])->delete();

        return $this->msg(1, "账号删除成功", null);
    }


    public function banAdmin()
    {
        $id = input("id");
        if (!is_numeric($id)) {
            return $this->msg(0, "参数错误", null);
        }

        $roleid = Db::name("sysadmin")->where("id", $id)->value("roleid");

        if ($roleid == 1) {
            return $this->msg(0, "超级管理员无法禁用", null);
        }

        $isdel = Db::name("sysadmin")->where("id", $id)->value("isdel");
        $data  = array();
        $msg   = "";
        if ($isdel == 0) {
            $data = array("isdel" => 1);
            $msg  = "管理员禁用成功";
        } else {
            $data = array("isdel" => 0);
            $msg  = "管理员启用成功";
        }

        $result = Db::name("sysadmin")->where(["id" => $id, "roleid" => 0])->update($data);

        return $this->msg(1, $msg, null);
    }


    public function test()
    {

        //echo urlencode(compile('12345'));
        //$company = new model\Company();
        //var_dump($company->getPlayer('20120'));

//        $proxy_code = "1";
//
//        $proxy_code = $proxy_code +1;
//        echo "HFH000000".$proxy_code;
        //$var=sprintf("HFH%07d", 2222);//生成4位数，不足前面补0
        // echo $var;//结果为0002

        //$usertemp = new \app\admin\model\UserTemplate();
        //$usertemp->Qrcode('123456');

//        $proxy =new \app\admin\model\Proxy();
//        $ret = $proxy->sendToDwj('20120');
//        print_r($ret);
//        print($ret->message);
        //$data =json_encode($ret,false);
        //return   $data->code.$data->message;
//        $mobile ="13405963356";
//        if(!$mobile){
//            return $this->showmsg(0, "参数错误", "", false, null);
//        }
//
//        $sms =new model\Sms();
//        $smsmodel = config('smsmodel');
//        if($smsmodel=='self') {
//            $code = rand(1000, 9999);
//            $ret = $sms->checkandsend($mobile, $code, 'checkout', get_ip());
//            if (!$ret) {
//                return $this->showmsg(0, "短信发送频率过快", "", false, null);
//            }
//        }
//        else if($smsmodel=='hff'){
//            $ret = $sms->send_sms($mobile);
//            print_r($ret);exit();
//            if($ret->code!=0){
//                return $this->showmsg(0, $ret->message, "", false, null);
//            }
//        }
        //return $this->showmsg(1, "短信发送成功", "", false, null);
    }


}
