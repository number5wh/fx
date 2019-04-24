<?php

namespace app\manage\controller;

use think\Controller;
use think\Session;
use think\Db;
use think\validate;

class  Base  extends  Controller{


    private $isVerifed = false;
    public $manageName = '';
    //public $roleid ='';
    public function _initialize() {
        //对session中用户名密码进行鉴权
        //成功进入首页，否则跳转登录页
//        $this->verify();
        if(!($this->verify())){
            $this->redirect('login/index');
        }

    }
        //退出

    public function check($captcha = '')
    {
        $data['captcha'] = $captcha;
        //实例化验证规则
        $validate = new Validate([
            'captcha|验证码' => 'require|captcha',
        ]);
        //检查验证结果
        if (!$validate->check($data)) {
            return false;
        } else {
            return true;
        }
    }


    public function verify(){
//        $this->isVerifed = true;
//        $proxy_name = Session::get('proxy_name');
//        $proxy_password = Session::get('proxy_password');
        $proxy = Session::get('manage');
        $proxyid = session("manage_id");
        if($proxy){
            $this->isVerifed = true;
        }else{
            $this->isVerifed = false;
        }
        return $this->isVerifed;
    }


//{"code":0,"msg":"昵称重复","error":null,"hasMore":false,"data":null}
    public function showmsg($code,$msg,$error,$hasMore,$data){
        $data = array(
            "code"=>$code,
            "msg"=>$msg,
            "error"=>$error,
            "hasMore"=>$hasMore,
            "data"=>$data
        );
        return  json($data);
    }


    public function msg($code,$msg,$data){
        return  $this->showmsg($code,$msg,'',false,$data);
    }


    protected function verifyCheck($id = '')
    {
        $verify = new Captcha();
        if (!$verify->check(input('verify'), $id)) {
            $this->error('验证码错误', url($this->request->module() . '/Login/login'));
        }
    }




}

