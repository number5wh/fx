<?php

namespace app\manage\controller;

use app\manage\controller\Base;
use think\Db;
use think\model;
use think\Paginator;
use think\log;
use app\manage\model\Sms;


class Proxy extends Base
{
    protected $rateData;
    public function _initialize() {
        $roleid = session("RoleId");
        if($roleid!=1){
            $this->error('您没有权限','index/index');
        }
    }

    public function __construct()
    {
        parent::__construct();
        $this->rateData = Db::table('proxypercent')->find(1);
    }

    public function index()
    {
        $sign = "wx_q8922030234";
        $this->assign("sign",$sign);
        return $this->fetch();
    }

    public function getIndex()
    {
        $status = input("status");
        $agentid = input("agentId");
        $phone = input("phone");
        $regstart = input("startTime");
        $regend = input("endTime");
        //$proxy = session("proxy");
        $minmoney = input("minMoney");
        $maxmoney = input("maxMoney");
        $nickname = input("nickName");
        $username = input("account");
        $page  = intval(input('page')) > 0 ? intval(input('page')) : 1;
        $limit = intval(input('limit')) > 0 ? intval(input('limit')) : 10;

        $where = array();
        //$where["parent_id"] =  $proxy["code"];
        if (!empty($status)) {
            $where["lock"] = $status;
        } else {
            $status = "";
        }


        if (!empty($agentid)) {
            $where['code'] = $agentid;
        }

        if (!empty($phone)) {
            $where['bind_mobile'] = $phone;
        }

        if (!empty($regstart)) {
            if (!empty($regend)) {
                $where['regtime'] = array(array('gt', $regstart), array('lt', $regend));
            } else {
                $where['regtime'] = array('gt', $regstart);
            }
        }

        if (!empty($minmoney)) {
            if (!empty($maxmoney)) {
                $where['balance'] = array(array('gt', $minmoney), array('lt', $maxmoney));
            } else {
                $where['balance'] = array('gt', $minmoney);
            }
        }

        if (!empty($nickname)) {
            $where["nickname"] = ['like', '%' . $nickname . '%'];;
        }

        if (!empty($username)) {
            $where["username"] = ['like', '%' . $username . '%'];;
        }
        //print_r($where);

        $datalist =array();
        $proxylist = Model("proxy");
        //查询满足要求的总的记录数
        //查询满足要求的总的记录数
        $sign = "wx_q8922030234";
        $count = Db::table('proxy')->where($where)->count();

        $list = Db::table('proxy')->where($where)->order('id desc ')->page($page, $limit)->select();
        foreach ($list as &$item) {
            $item['md5token'] = md5($sign.$item['code'].$item['password']);
            if ($item["bind_ip"] == '')
                $item["bind_ip"] = "否";
            else
                $item["bind_ip"] = "是";


            if ($item["lock"] == '1')
                $item["lock"] = "是";
            else
                $item["lock"] = "否";
        }
        unset($item);
        return json(['code' => 0, 'count' => $count, 'data' => $list]);
    }


    public function editProxyInfo()
    {
        $id = input("id");
        //$proxy = session("proxy");
        if (!$id) {
            return $this->showmsg(0, "参数为空", '', '', null);
        }
        $proxyInfo = Db::name("proxy")->where("id", $id)->find();
        if (!$proxyInfo) {
            return $this->showmsg(0, "找不到该代理信息", '', '', null);
        }
//        //判断当前代理是否是编辑人下级
//        if ($proxyInfo['parent_id'] != $proxy['code']) {
//            return $this->showmsg(0, "无权限修改此代理信息", '', '', null);
//        }

        $canset = 0; //能否设置比例
//        if ($proxyInfo['grade'] <= 2) {//管理员或总代理
//            $percent = $this->rateData['general_rate'];
//            $info = '可设最大值为：'.$percent;
//            $canset = 1;
//        } else {
//            if ($proxyInfo['percent'] >= $this->rateData['level1_rate']) {//下级代理是否已超出限定值
//                $info = '';
//            } else {
////                $percent = $proxy['percent'];
//                //获取此代理的上级代理
//                $parentProxy = Db::name("proxy")->where("code", $proxyInfo['parent_id'])->find();
//
//                $maxPercent =  $parentProxy['percent'] > $this->rateData['level1_rate'] ? $this->rateData['level1_rate'] : $parentProxy['percent']-1;
//                //$percent = $this->rateData['level1_rate'];
//                $info = '可设最大值为：'.$maxPercent;
//                $canset = 1;
//            }
//        }


//                $percent = $proxy['percent'];
            //获取此代理的上级代理
        $percent = $this->rateData['general_rate'];//可设值的最大值与上级比例比较`
        $parentProxy = Db::name("proxy")->where("code", $proxyInfo['parent_id'])->find();

        $maxPercent = $percent>$parentProxy['percent'] ?  $parentProxy['percent']-1 : $percent;
        //$percent = $this->rateData['level1_rate'];
        $info = '可设最大值为：'.$maxPercent;
        $canset = 1;



        return json(['code'=>1, 'info2'=>$info,'canset'=>$canset]);

    }

    public function editProxy()
    {
        $code = input("proxy_code");
        $passord = input("proxy_password");
        $nickname = input("proxy_nickname");
        $percent = intval(input("proxy_rate"));
        $remark = input("proxy_comment");
       // $proxy = session("proxy");
        if (!$code  || $nickname == '' || $percent == '') {
            return $this->showmsg(0, "参数为空", '', '', null);
        }
        if ($passord && strlen($passord) <6) {
            return $this->showmsg(0, "密码长度至少为6位", '', '', null);
        }
        $proxyInfo = Db::name("proxy")->where("code", $code)->find();
        if (!$proxyInfo) {
            return $this->showmsg(0, "找不到该代理信息", '', '', null);
        }
//        //判断当前代理是否是编辑人下级
//        if ($proxyInfo['parent_id'] != $proxy['code']) {
//            return $this->showmsg(0, "无权限修改此代理信息", '', '', null);
//        }
        //昵称
        $data = Db::name("proxy")->where("nickname", $nickname)->find();
        if ($data && $data['code'] !=$code) {
            return $this->showmsg(0, "昵称已存在", '', '', null);
        }

        //分成比例的判断
//        if ($proxyInfo['grade'] <= 2) {//管理员或总代理
//            if ($percent > $this->rateData['general_rate']) {
//                return $this->showmsg(0, "提成比例不能大于" . $this->rateData['general_rate'], '', '', null);
//            }
//            if ($percent < $proxyInfo['percent']) {
//                return $this->showmsg(0, "设置的提成比例不能小于原有的提成比例", '', '', null);
//            }
//        } else {
//            if ($proxyInfo['percent'] >= $this->rateData['level1_rate']) {//下级代理是否已超出限定值55
//                if ($percent != $proxyInfo['percent']) {
//                    return $this->showmsg(0, "当前提成比例不能修改", '', '', null);
//                }
//            } else {
//                //可设置最大值
//                //获取此代理的上级代理
//                $parentProxy = Db::name("proxy")->where("code", $proxyInfo['parent_id'])->find();
//
//                $maxPercent =  $parentProxy['percent'] > $this->rateData['level1_rate'] ? $this->rateData['level1_rate'] : $parentProxy['percent']-1;
//                if ($percent < $proxyInfo['percent']) {
//                    return $this->showmsg(0, "设置的提成比例不能小于原有的提成比例", '', '', null);
//                }
//                if ($percent > $maxPercent) {
//                    return $this->showmsg(0, "设置的提成比例不能大于".$maxPercent, '', '', null);
//                }
//            }
//        }

        if ($percent > $this->rateData['general_rate']) {
            return $this->showmsg(0, "提成比例不能大于" . $this->rateData['general_rate'], '', '', null);
        }
        if ($percent < $this->rateData['lowest_rate']) {
            return $this->showmsg(0, "提成比例不能小于" . $this->rateData['lowest_rate'], '', '', null);
        }

        //可设置最大值
        //获取此代理的上级代理
        $parentProxy = Db::name("proxy")->where("code", $proxyInfo['parent_id'])->find();

        $maxPercent = $this->rateData['general_rate'] >  $parentProxy['percent']  ? $parentProxy['percent']-1 : $this->rateData['general_rate'];
        if ($percent > $maxPercent) {
            return $this->showmsg(0, "设置的提成比例不能大于".$maxPercent, '', '', null);
        }



        $update = [
            'nickname' => $nickname,
            'percent' => $percent
        ];
        if ($passord) {
            $salt = strtolower(generateSalt());
            $update['password'] = md5($salt.$passord);
            $update['salt'] = $salt;
        }
        if ($remark) {
            $update['descript'] = $remark;
        }
        $res = Db::name("proxy")->where('code',$code)->update($update);
        if ($res) {
            return $this->showmsg(1, "更新成功", '', '', null);
        } else {
            return $this->showmsg(1, "更新失败", '', '', null);
        }

    }




    public function getById()
    {
        $id = input('id');
        $res = Db::table('proxy')->where(['id' => $id])->find();
        return json(['code' => 0, 'data'=>$res, 'msg' => '']);
    }



    public function Changepwd()
    {
        return $this->fetch();
    }


    public function editagentmobile(){
        $pid =input("pid");
        $mobile =input("phonenum");

        if(empty($pid) || $mobile==""){
            return $this->showmsg(0, "参数有错", "", false, null);
        }

        preg_match_all("/\d{11}/",$mobile,$result);

        if(!is_array($result)){
            return $this->showmsg(0, "请正确输入手机号码", "", false, null);
        }

        $proxy= Db::name("proxy")->where('id',$pid)->find();

        if(is_array($proxy)){
            $data["bind_mobile"] =$mobile;
            $ret = Db::name("proxy")->where('id',$pid)->update($data);

        }
        return $ret;
    }



    /*
     * 修改账号密码
     */
    public function modifierPassword()
    {
        $oldPwd = input('oldPassword');
        $newPwd = input("password");
        $confirmPwd = input('passwordConfirm');

        $proxy = session("manage");
        $decrytPwd = md5($proxy['salt'] . $oldPwd);

        if ($decrytPwd === $proxy['password']) {
            $newPwd = md5($proxy['salt'] . $newPwd);
            $data = array('password' => $newPwd);
            Db::name("sysadmin")->where('id', $proxy['id'])->update($data);
            $proxy = Db::name("sysadmin")->where('id', $proxy['id'])->find();
            session('manage', $proxy);
            return true;
        } else {
            return false;
        }
    }



    /*
     * 检查验证码发送短信
     */
    public function checkmobile()
    {
        $phone = input("phone");
        $captcha = input("captcha");

        if (empty($phone) || empty($captcha)) {
            return $this->showmsg(0, "必须收入手机号码", "", false, null);
        }
        if (!$this->check($captcha, "bindphone")) {
            return $this->showmsg(0, "输入验证码不正确", "", false, null);
        }
        return false;
    }




    public function checkresetpassword(){
        $code = input("code");
        $mobile = input("codeMsg");

        if(empty($code) || empty($mobile)){
            return $this->showmsg(0, "参数错误", "", false, null);
        }

        $sms = new Sms();
        $status = $sms->checkcode($mobile,$code,'resetpwd');
        if($status){
            session("resetcheckpass","isPass");
            return $this->fetch("proxy/checkpasssave");
        }
        else
        {
            return $this->error("验证码不正确");
        }
    }


    public function resetSettlementPassword(){
        $passord = input("password");
        $flag = session("resetcheckpass");
        if(isset($flag))
        {
            $proxy = session("proxy");
            $checkpass = md5($proxy['salt'].$passord);
            $data = array('check_pass'=>$checkpass);
            $status = Db::name("proxy")->where(['code'=>$proxy['code']])->update($data);

            if($status){
                return $this->success("验证密码修改成功",url("index/main"));
            }
            else
            {
                return $this->error("验证密码修改失败，请重试");
            }
        }
    }



    public function getAgentDetail(){
        $id = input("id");
//        if(!is_numeric($id)){
//            return $this->msg(0,"参数错误",null);
//        }
       

        $proxy= Db::name("proxy")->where("code",$id)->find();
        $resp = array();
        if(!empty($proxy)){

            $resp["agentId"] = $proxy["code"];

            $recharge = Db::name("paytime")->where("proxy_id",$proxy["code"])->sum('totalfee');

            $resp["recharge"] =$recharge;// $proxy[""];

            $resp["extractSum"] = 0;//$proxy[""];
            //税收
            $resp["revenue"] = 0;//$proxy[""];
            $resp["income"] = $proxy["historyin"];
            $reg = Db::name("player")->where("proxy_id",$proxy["code"])->count();
            $resp["reg"] = $reg;

            //总提现金额
            $withdrawDeposit = Db::name("checklog")->where("proxy_id",$proxy["code"])->where("status",3)->sum('amount');
            $resp["withdrawDeposit"] =$withdrawDeposit;// $proxy[""];
            $resp["royaltyRate"] =0;// $proxy[""];
            $resp["maxRoyaltyRate"] =0;// $proxy[""];
            $resp["money"] = $proxy["balance"];
            $resp["maxRoyaltyRate"] =0;// $proxy[""];
            $this->assign('detail', $resp);
            return $this->fetch('detail');
            //return $this->msg(1,"获取成功",$resp);
        }
        else
        {
            $this->fetch('detail', []);
            return $this->fetch('detail');
            //return $this->msg(0,"获取失败",null);
        }
    }



    public function editAgent(){
        //$percent =input("royaltyRate");
        $changeRate =input("changeRate");
        $status =input("status");
        $nickname =input("nickname");
        $id = input("id");
        $remark =input("remark");

        if($status==1) {
//            if (!is_numeric($percent)) {
//                return $this->msg(0, "参数错误1", null);
//            }
        }

        if(!is_numeric($status)){
            return $this->msg(0, "参数错误2", null);
        }
        else
        {
            if($status!=0 && $status!=1)
            {
                return $this->msg(0, "参数错误3", null);
            }
        }
        $data =array("nickname"=>$nickname,"descript"=>$remark,"islock"=>$status);
//        if($changeRate==1){
//            $data['percent']=$percent;
//        }
        $result = Db::name("proxy")->where("id",$id)->update($data);
        if($result)
            return $this->msg(1,"提交成功",null);
        else
            return $this->msg(0,"提交失败",null);

    }






}