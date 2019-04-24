<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/22
 * Time: 22:14
 */
namespace app\manage\model;
use think\Model;

class Proxy extends Model{


    public function login($username = '', $password = '', $rememberme = false)
    {
        $username = trim($username);
        $password = trim($password);
        $user     = Db::table('proxy')->where('username', $username)->find();
        if (!$user) {
            $this->error = '管理员不存在';
        } else {
            if (encrypt_password($password, $user['salt']) !== $user['password']) {
                $this->error = '密码错误！';
            } else {
                $aid = $user['id'];
                // 更新登录信息
                $user['last_ip']   = request()->ip();
                $user['last_time'] = time();
                $user['logtimes']  = $user['logtimes'] + 1;
                if ($user->save()) {
                    // 自动登录
                    $this->autoLogin($user, $rememberme);
                }
                return $aid;
            }
        }
        return false;
    }

    /**
     * 自动登录
     *
     * @param mixed $user       用户对象
     * @param bool  $rememberme 是否记住登录，默认7天
     * @throws
     */
    private function autoLogin($Proxy, $rememberme = false)
    {
        // 记录登录
        $auth = [
            'id'                  => $Proxy->id,
            'code'                => $Proxy->code,
            'avatar'               => $Proxy->avatar,
            'realname'             => $Proxy->username,
            'username'             => $Proxy->username,
            'last_ip'              => $Proxy->last_ip,
            'last_time'            => $Proxy->last_time
        ];
        session('admin_auth', $auth);
        session('admin_auth_sign', data_signature($auth));

        // 记住登录
        if ($rememberme) {
            $signin_token ="";// $Proxy->username . $user->id . $user->last_time;
            cookie('aid', $user->id, 24 * 3600 * 7);
            cookie('signin_token', data_signature($signin_token), 24 * 3600 * 7);
        }
        //根据需要决定是否记录前台登陆
        session('hid', $auth['uid']);
        cookie('logged_user', jiami("{$auth['uid']}.{$auth['last_time']}"));
        $user_model = new UserModel();
        $user = $user_model->where('id', $auth['uid'])->find();
        if ($user) {
            session('user', $user);
        }
    }





    public function isLogin()
    {
        $user = session('admin_auth');
        if (empty($user)) {
            if (cookie('?aid') && cookie('?signin_token')) {
                $user = $this::get(cookie('aid'));
                if ($user) {
                    $signin_token = data_signature($user['username'] . $user['id'] . $user['last_time']);
                    if (cookie('signin_token') == $signin_token) {
                        $this->autoLogin($user, true);
                        return $user['id'];
                    }
                }
            };
            return 0;
        } else {
            return session('admin_auth_sign') == data_signature($user) ? $user['aid'] : 0;
        }
    }




    public function  sendToDwj($proxy_id){
        vendor('Hprose.Hprose');
        $client = \Hprose\Client::create('http://apiservice.gamexkd.com/service.php',false);
        try {
            $timestamp =getservertime();
            $appkey =config('appkey');
            $token =md5($appkey.$proxy_id.$timestamp);
            $client->addFilter(new \Hprose\SizeFilter('Non compressed'))
                ->addFilter(new \Hprose\CompressFilter())
                ->addFilter(new \Hprose\SizeFilter('Compressed'));
            $jsonstr = $client->Pack($proxy_id,$timestamp,$token,new \Hprose\InvokeSettings(array('timeout'=>30000)));
            return json_decode($jsonstr,false);
        }catch (Exception $e) {
            return  false;
        }

    }








}