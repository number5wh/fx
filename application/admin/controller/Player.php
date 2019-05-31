<?php
namespace app\admin\controller;

use think\Db;
use think\Request;
/**
 * 人人代管理
 */
class Player extends Base
{

    private $db2;
    public function __construct()
    {
        parent::__construct();
        $this->db2 = config('database_spread.database');
    }


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

            $userid = input('userid'); //玩家id
            $start = input('start');//时间
            if (!$start) {
                $start = date('Y-m-d', strtotime('-1day'));
            }

            $proxy = session("proxy");

            $where = ['proxy_id' => $proxy['code']];
            if ($userid) {
                $where['userid'] = $userid;
            }
            $count = Db::name('player')->where($where)->count();
            $data['count'] = $count;
            if (!$count) {
                return json($data);
            }
            $list = Db::name('player')
                ->where($where)
                ->field('userid, nickname')
                ->page($page, $limit)
                ->select();
            foreach ($list as &$v) {
                //从spread获取玩家统计数据
                $v['percent'] = config('spreadrate');
                $v['day'] = $start;
                //获取推广玩家信息
                $userlist = Db::connect($this->db2)->name('teamlevel')->where(['parent_id' => $v['userid']])->select();
                $userlist = array_column($userlist, 'roleid');
//                array_unshift($userlist, $v['userid']);
                $v['num'] = count($userlist);//推广玩家总数
                //获取推广总税收
                $v['totaltax'] = Db::connect($this->db2)->name('incomelog')->where(['roleid' => $v['userid']])->sum('totaltax');
                //当日税收
                $v['daytax'] = Db::connect($this->db2)->name('incomelog')->where(['roleid' => $v['userid'], 'createtime'=>['like',$start.'%']])->sum('totaltax');
                //当日收入
                $v['dayincome'] = Db::connect($this->db2)->name('incomelog')->where(['roleid' => $v['userid'], 'createtime'=>['like',$start.'%']])->sum('changmoney');
                //当日上级收入
                $v['proxyincome'] = Db::name('incomelog')->where(['userid' => $v['userid'],'proxy_id'=> $proxy['code'],'fxtype' => 1,'typeid' => config("incomelog.spread"), 'addtime' => ['like',$start.'%']])->sum('changmoney');
            }
            unset($v);

            //$count = Db::connect($this->db2)->name('playerorder')->count();

            $data['data'] = $list;
            return json($data);
        } else {
            $proxy = session("proxy");
            $income = Db::name('incomelog')->where(['proxy_id'=> $proxy['code'],'fxtype' => 1,'typeid' => config("incomelog.spread")])->sum('changmoney');
            $this->assign('income',$income);
            return $this->fetch();
        }
    }
}
