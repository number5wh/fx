<?php

namespace app\admin\controller;

use app\admin\controller\Base;
use app\manage\model\WithdrawBank;
use think\Db;
use think\captcha\Captcha;
use app\admin\model;
use think\log;

class Settle extends Base
{

    public function _initialize()
    {
        parent::_initialize();
        if (session('proxy')['type'] == 1) {
            return $this->showmsg(0, "您没有权限", '', false, null);
        }
    }

    public function updateSettlementAccount()
    {

        $aliAccount = input("aliAccount");
        $realname   = input("realName");

//        $cardAccount = input("cardAccount");
//        $bank = input("bank");
//        $name = input("name");

        $password = input("settlementPassword");
        $code     = input("code");
        $codeMsg  = input("codeMsg");

        $proxy_id  = session("proxy_id");
        $proxy     = Db::name("proxy")->where("code", $proxy_id)->find();
        $checkpass = md5($proxy["settle_salt"] . $password);
//        print($proxy['check_pass']);
//        print($checkpass);
        if ($proxy['check_pass'] != $checkpass) {
            return $this->showmsg(0, "验证结算密码不正确，请重输！", '', false, null);
        }

        $sms = new \app\admin\model\Sms();
        if( config('smsmodel')=="self"){
            $status = $sms->checkcode($proxy["bind_mobile"],$code,'setaccount');
            if(!$status){
                return $this->showmsg(0,"短信验证码输入错误，请重输！",'',false,null);
            }
        }
        else if( config('smsmodel')=="hff"){
            $status = $sms->validateSms($proxy["bind_mobile"],$code);//$sms->checkcode($mobile,$code,'resetpwd');
            //print("smsvalidate:".json_encode($status));
            if($status->code!=0){
                return $this->showmsg(0,"短信验证码输入错误，请重输！",'',false,null);
            }
        }


        $total = Db::name("bankinfo")->where("proxy_id", $proxy['code'])->count();
        $data  = array("alipay" => $aliAccount, "alipay_name" => $realname);//, "cardAccount"=>$cardAccount,"bank"=>$bank,"name"=>$name

        $status = false;
        if ($total > 0) {
            $status = Db::name("bankinfo")->where("proxy_id", $proxy["code"])->update($data);
        } else {
            $data["proxy_id"] = $proxy["code"];
            $status = Db::name("bankinfo")->insertGetId($data);
        }
        if ($status) {
           return $this->showmsg(1, "更新账户信息成功", '', false, null);
        } else {
           return $this->showmsg(0, "更新失败，请稍后重试", '', false, null);
        }
    }


    public function updateSettlementBank()
    {
//        $aliAccount = input("aliAccount");
////        $realname = input("realName");

        $cardAccount = input("cardAccount");
        $bank        = input("bank");
        $name        = input("name");

        $password = input("settlementPassword");
        //$code     = input("code");
        $codeMsg  = input("codeMsg");
        $city  = input("city");
        $province  = input("province");


        $proxy_id  = session("proxy_id");
        $proxy     = Db::name("proxy")->where("code", $proxy_id)->find();
        $checkpass = md5($proxy["settle_salt"] . $password);
        //print($proxy['check_pass']);
        if ($proxy['check_pass'] != $checkpass) {
            return $this->showmsg(0, "结算密码不正确，请重输！", '', false, null);
        }

//        $sms = new \app\admin\model\Sms();
//        $status = $sms->checkcode($proxy["bind_mobile"],$code,'setaccount');
//        if(!$status){
//            return $this->showmsg(0,"短信验证码输入错误，请重输！",'',false,null);
//        }
//        $sms    = new \app\admin\model\Sms();
//        $status = $sms->validateSms($proxy["bind_mobile"], $code);
//        if ($status->code != 0) {
//            return $this->msg(0, "短信验证码不正确，请重输！", null);
//        }

        $find  = Db::name("bankinfo")->where("proxy_id", $proxy['code'])->find();
        $data   = array("cardaccount" => $cardAccount, "bank" => $bank, "name" => $name, 'city' => $city, 'province' => $province);

        $status = false;
        $withdraw = new WithdrawBank();
        if ($find) {
            if ($find['cardaccount'] == $cardAccount && $find['bank'] ==$bank && $find['name'] ==$name && $find['city'] ==$city && $find['province'] ==$province  ) {
                return $this->showmsg(0, "信息未做修改", '', false, null);
            }

            //更新第三方银行卡信息

            $res = $withdraw->addCard($cardAccount,$name,$bank,$city,$province);
            $res2 = json_decode($res, true);
            if ($res2['success'] != true) {
                $res = $withdraw->modifyCard($cardAccount,$name,$bank,$city,$province);
            }
            save_log("apidata/bindbank",$proxy["code"].$res);
            $res2 = json_decode($res, true);
            if ($res2["success"] == true) {
                $status = Db::name("bankinfo")->where("proxy_id", $proxy["code"])->update($data);
            }
        } else {
            $data["proxy_id"] = $proxy["code"];
            $res = $withdraw->addCard($cardAccount,$name,$bank,$city,$province);
            $res2 = json_decode($res, true);
            if ($res2['success'] != true) {
                $res = $withdraw->modifyCard($cardAccount,$name,$bank,$city,$province);
            }
            save_log("apidata/bindbank",$proxy["code"].$res);
            $res2 = json_decode($res, true);
            if ($res2["success"] == true) {
                $status = Db::name("bankinfo")->insert($data);
            }
        }

        if ($status) {
            return $this->showmsg(1, "更新账户信息成功", '', false, null);
        } else {
            return $this->showmsg(0, "更新失败，请稍后重试", '', false, null);
        }
    }


    public function settlementlog()
    {

        $status     = input("condition");
        $startime   = input("startTime");
        $endtime    = input("endTime");
        $alipay     = input("alipayAccount");
        $alipayName = input("realName");
        $agent_id   = input("agentId");


        $proxy_id = session("proxy_id");
        $where    = array();
        if (!empty($status)) {
            $where["a.status"] = $status;
        }


        if (!empty($startime)) {
            if (!empty($endtime)) {
                $where['addtime'] = array(array('gt', strtotime($startime)), array('lt', strtotime($endtime)));
            } else {
                $where['addtime'] = array('gt', strtotime($startime));
            }
        }


        if (!empty($alipay)) {
            $where["a.alipay"] = $alipay;
        }

        if (!empty($alipayName)) {
            $where["a.alipay_name"] = array("like", '%' . $alipayName . '%');
        }

        $strwhere = "";
        if (!empty($agent_id)) {
            $strwhere = " a.proxy_id in (select `code` from proxy where parent_id='" . $proxy_id . "' and code='" . $agent_id . "') ";
            if (!empty($status)) {
                $strwhere .= " and status=" . $status;
            }
            if ($proxy_id == $agent_id) {
                $where["a.proxy_id"] = $proxy_id;
            }
            if (!empty($alipay)) {
                $strwhere .= " and a.alipay=" . $alipay;
            }
        } else {
            $where["a.proxy_id"] = $proxy_id;
            $strwhere            = " a.proxy_id in (select `code` from proxy where parent_id='" . $proxy_id . "')";
            if (!empty($status)) {
                $strwhere .= 'and a.status=' . $status;
            }
            if (!empty($alipay)) {
                $strwhere .= " and a.alipay='" . $alipay . "'";
            }
        }


        $checklist = Db::name("checklog")->alias('a')
            ->field('a.id,a.orderid,a.proxy_id,a.tax,a.amount,a.alipay,a.name,a.bank,a.cardaccount,a.alipay_name,a.checktype,a.status,a.addtime,a.createtime,a.descript,c.nickname,c.username')
            ->join('proxy c', ' a.proxy_id=c.code', "LEFT")
            ->where($where)->whereor($strwhere)
            ->paginate(10, false, ['query' => request()->param()])
            ->each(function ($item, $key) {
                $item['cardaccount'] = substr($item['cardaccount'], 0, -4) . '****';
                $item['name'] = mb_substr($item['name'], 0, 1).'**';
//                var_dump(substr($item['cardaccount'], 0, -4),$item['cardaccount']);
//                die;
                return $item;
            });
        $this->assign("status", $status);
        $this->assign("starttime", $startime);
        $this->assign("endtime", $endtime);
        $this->assign("list", $checklist);
        $this->assign("alipay", $alipay);
        $this->assign("alipayname", $alipayName);
        $this->assign("agentid", $agent_id);
        return $this->fetch();
    }


    /*
     * 提交结算
     */
    public function submitSettlement()
    {
        $proxy_id = session("proxy_id");
        $proxy    = Db::name("proxy")->where('code', $proxy_id)->find();
        $bankinfo = Db::name("bankinfo")->where('proxy_id', $proxy['code'])->find();
        $jsonAli  = null;
        $jsonBank = null;
        if (!empty($bankinfo['alipay'])) {
            $jsonAli = array("agentId" => $bankinfo['proxy_id'], 'aliAccount' => $bankinfo['alipay'], "realName" => $bankinfo['alipay_name']);
        }
        if (!empty($bankinfo['cardaccount'])) {
            $jsonBank = array("agentId" => $bankinfo['proxy_id'], 'cardAccount' => $bankinfo['cardaccount'], "Bank" => $bankinfo['bank'], "Name" => $bankinfo['name'], 'city' => $bankinfo['city'],'province'=>$bankinfo['province']);
        }
        $this->assign("proxy", $proxy);
        $this->assign("bankinfo", $bankinfo);
        $this->assign("alipay", json_encode($jsonAli));
        $this->assign("bank", json_encode($jsonBank));
        return $this->fetch("settle/submitsettlement");
    }


    public function applySettlement()
    {
        $proxy_id           = session("proxy_id");
        $money              = input("money");
        $settlementPassword = input("settlementPassword");
//        $code = input("code");
        $type = input("type");


        if (empty($money) || empty($type) || empty($settlementPassword)) {
            return $this->msg(0, "提交的参数不能为空！", null);
        }

        if (!is_numeric($money)) {
            return $this->msg(0, "提交的金额！", null);
        }

        $proxy = Db::name("proxy")->where('code', $proxy_id)->find();

        $settlepwd = md5($proxy['settle_salt'] . $settlementPassword);

        if ($proxy['check_pass'] != $settlepwd) {
            return $this->showmsg(0, "结算密码不正确，请重输！", '', false, null);
        }


//        $sms = new \app\admin\model\Sms();
//        $status = $sms->validateSms($proxy["bind_mobile"],$code);
//        if($status->code!=0){
//            return $this->msg(0,"短信验证码不正确，请重输！",null);
//        }

        $taxMoney = 0;
        if (config("isTax")) {
            if ($money * 0.02 < 3)
                $taxMoney = 3;
            else
                $taxMoney = $money * 0.02;
        }

        if ($proxy['balance'] < $money + $taxMoney) {
            return $this->msg(0, "结算金额大于您账户金额，请更改金额！", '', null);
        }


        $orderid = date('Ymd') . rand(100000, 999999);
        $yy      = $proxy["balance"] - $money - $taxMoney;
        $data    = array("orderid"   => $orderid, "proxy_id" => $proxy_id, "amount" => $money, "balance" => $yy,
                         "checktype" => $type, "status" => 1, "addtime" => time(), "tax" => $taxMoney);


        $bankinfo = Db::name("bankinfo")->where('proxy_id', $proxy_id)->find();
        if ($type == 2) {
            if (!$bankinfo['bank'] || !$bankinfo['name']|| !$bankinfo['cardaccount']|| !$bankinfo['province']|| !$bankinfo['city']) {
                return $this->msg(0, "请点击结算账户更新银行卡信息及其省份信息", '', null);
            }
            $data['bank']        = $bankinfo['bank'];
            $data['name']        = $bankinfo['name'];
            $data['cardaccount'] = $bankinfo['cardaccount'];
            $data['province'] = $bankinfo['province'];
            $data['city'] = $bankinfo['city'];

        } else if ($type == 1) {
            if (!$bankinfo['alipay'] || !$bankinfo['alipay_name']) {
                return $this->msg(0, "请先绑定支付宝账户", '', null);
            }
            $data['alipay']      = $bankinfo['alipay'];
            $data['alipay_name'] = $bankinfo['alipay_name'];
        } else {
            return $this->msg(0, "提现方式有误！", '', null);
        }

        $data['descript']   = $proxy['nickname'] . ",id" . $proxy['code'] . '于' . date('Y-m-d H:i:s') . '提现金额' . $money . '元';
        $data['addtime']    = date("Y-m-d H:i:s");
        $data['createtime'] = time();
        Db::startTrans();
        try {
            $account_data = array("balance" => $yy);
            $ret          = Db::name("proxy")->where("code", $proxy_id)->update($account_data);
            if (!empty($ret)) {
                $ret = Db::name("checklog")->insert($data);
                if ($ret) {
                    $moneyInfo = Db::name("proxy")->where("code", $proxy_id)->field('balance,historyin')->find();
                    //金额log
                    Db::name('moneylog')->insert([
                        'type' => 1,
                        'detail' => 3,
                        'tax' => 0,
                        'money' => $money + $taxMoney,
                        'leftmoney' => $moneyInfo['balance'],
                        'historyin' => $moneyInfo['historyin'],
                        'proxy_id'  => $proxy_id,
                        'createtime' => date("Y-m-d H:i:s"),
                        'createday' => date("Ymd")
                    ]);

                    $data1 = array(
                        "typeid"     => 1,
                        "proxy_id"   => $proxy['code'],
                        "changmoney" => $money,
                        "descript"   => "用户提现，金额" . $money . ",税收." . $taxMoney,
                        "createtime" => time()
                    );
                    Db::name("incomelog")->insert($data1);
                }
                //Log::write('charu=='.$ret,"debug");
            }
            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            //Log::write('charu=='.$e->getMessage(),"debug");
            // 回滚事务
            Db::rollback();
        }
        return $this->msg(1, "提交成功，请等待审核，审核成功后自动放款！", null);
    }


}