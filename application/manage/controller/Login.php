<?php

namespace app\manage\controller;

use think\Controller;
use think\Session;
use think\Db;
use think\Validate;
use think\model;
use think\captcha\Captcha;


class Login extends Controller
{

    public function Index()
    {
        $error_html = "";
        $this->assign('error', $error_html);
        return $this->fetch();
    }

    public function Auth()
    {
        $error = "";
        //captcha_img();
        $verify_code = trim(input('captcha'));
        echo $verify_code;
        if ($this->check($verify_code)) {

            $username =input('userName');
            $salt = Db::name('sysadmin')->where(['username' => input('userName')])->value('salt');
            $map['username'] = input('userName');
            $map['password'] = md5($salt . input('password'));
            $proxy = Db::name('sysadmin')->where($map)->find();
            if ($proxy) {
                if($proxy['isdel']==1){
                    $error = "账号已被禁用";
                    $error_html = '<label style="color: red">' . $error . '</label>';
                    $this->assign('error', $error_html);
                    return $this->fetch('login/index');
                }
                Session::set('manage', $proxy);
                Session::set('manage_id', $proxy["id"]);
                Session::set('RoleId', $proxy["roleid"]);
                $this->redirect('index/index');

            } else {
                $error = "用户名密码错";
                $error_html = '<label style="color: red">' . $error . '</label>';
                $this->assign('error', $error_html);
                return $this->fetch('index');
            }

        } else {
            $error = "验证码错误";
            $error_html = '<label style="color: red">' . $error . '</label>';
            $this->assign('error', $error_html);
            //echo "失败";exit();
            $this->redirect('login/index');
        }
    }

    public function check($code = '')
    {
        //$data['captcha'] = $captcha;
        $captcha = new Captcha();
        $ret = $captcha->check($code,"adminlogin");
        return $ret;
        //实例化验证规则
//        $validate = new Validate([
//            'captcha|验证码' => 'require|captcha',
//        ]);
//        //检查验证结果
//        if (!$validate->check($data)) {
//            return false;
//        } else {
//            return true;
//        }
    }


    public function verifycode()
    {
        $config =array(
            'length'   => 4,
//            'fontSize' => 25,
//            // 验证码字体大小(px)
//            'useCurve' => true,
            // 是否画混淆曲线
            'useNoise' => false,
            'useCurve' => false
//            // 是否添加杂点
//            'imageH'   => 50,
//            // 验证码图片高度
//            'imageW'   => 300,
        );
        ob_clean();
        $captcha = new Captcha($config);
        $captcha->codeSet = '0123456789';
        return $captcha->entry("adminlogin");
    }

    public function logout()
    {
       // session('admin_auth', null);
      //  session('admin_auth_sign', null);
       // cookie('aid', null);
       // cookie('signin_token', null);
        session('manage',null);
        session('manage_id',null);
        $this->redirect(url('Login/index'));
    }


    public function getProxy(){
        $proxy_id ="WZ0000048";
        $regtotal = Db::name("player")->where(["proxy_id",$proxy_id])->count();
        $totaltax = Db::name("playerorder")
            ->alias("a")
            ->join("proxy b","a.proxy_id=b.code","LEFT")
            ->where(["a.proxy_id",$proxy_id])
            ->sum("a.total_tax");

        $totaltax2 = Db::name("playerorder")
            ->alias("a")
            ->join("proxy b","a.proxy_id=b.code","LEFT")
            ->where(["b.parent_id",$proxy_id])
            ->sum("a.total_tax");


        echo  json_encode(array("player"=>$regtotal,"tt"=>$totaltax2+$totaltax));

    }

}
