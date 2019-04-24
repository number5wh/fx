<?php
namespace app\admin\controller;
use think\Controller;
use think\Session;
class Filter extends Controller{
    private $isVerifed = false;
    public function _initialize() {
        //对session中用户名密码进行鉴权
        //成功进入首页，否则跳转登录页
//        $this->verify();
        if(!($this->verify())){
             $this->redirect('admin/login/index');
        }
    }
    
    public function verify(){
//        $this->isVerifed = true;
//        $proxy_name = Session::get('proxy_name');
//        $proxy_password = Session::get('proxy_password');
        $proxy = Session::get('proxy');
        $proxyid = session("proxyid");
        if($proxy){
            $this->isVerifed = true;
        }else{
            $this->isVerifed = false;
        }
        return $this->isVerifed;
    }
}

