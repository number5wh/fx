<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

class Playergame extends Base
{
    public function _initialize()
    {
        parent::_initialize();
        if (session('proxy')['type'] == 0) {
            return $this->showmsg(10,"您没有权限",'',false,null);
        }
    }

    //玩家游戏记录
    public function index()
    {
        if (Request::instance()->isAjax()) {
            $data = [
                'code' => 0,
                'msg' => '',
                'count' => 0,
                'data' => []
            ];
            $page  = (intval(input('page')) > 0) ? intval(input('page')) : 1;
            $limit = (intval(input('limit')) > 0) ? intval(input('limit')) : 10;


            $count = Db::name('playergame')
                //->where(['proxy_id' => session('code')])
                ->count();
            $data['count'] = $count;
            if (!$count) {
                return json($data);
            }
            $list = Db::name('playergame')
                //->where(['proxy_id' => session('proxy_id')])
                ->field('id, userid, roomname, changemoney, addtime')
                ->page($page, $limit)
                ->select();
            $data['data'] = $list;
            return json($data);
        } else {
            return $this->fetch();
        }
    }



    //每日玩家输赢
    public function playerlog()
    {
        if (Request::instance()->isAjax()) {
            $loginid= input("loginid");
            $mobile = input("mobile");
            $date = input("date");
            $model = new \app\admin\model\Playergame();
            $res = $model->getFxUserGameWin($loginid,$mobile,$date);
            if ($res->code == 0) {
                return json(['code' => $res->code, 'msg' => $res->message, 'data' => $res->data]);
            } else {
                return $this->showmsg(1,"暂无数据",'',false,null);
            }
        } else {
            return $this->fetch();
        }
    }

}
