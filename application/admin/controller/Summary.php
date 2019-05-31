<?php
namespace app\admin\controller;

use think\Db;
use think\Request;
/**
 * 经营汇总(渠道有)
 */
class Summary extends Base
{

    public function __construct()
    {
        parent::__construct();
    }

    public function _initialize()
    {
        parent::_initialize();
        if (session('proxy')['type'] != 2 && session('proxy')['code'] != 1) {
            //非渠道&&管理员
            exit('您没有权限');
        }
    }

    //代理业绩明细
    public function proxydetail()
    {
        if (Request::instance()->isAjax()) {
            $data = [
                'code' => 0,
                'msg' => '',
                'count' => 0,
                'data' => [],
                'income' => 0
            ];
            $page  = (intval(input('page')) > 0) ? intval(input('page')) : 1;
            $limit = (intval(input('limit')) > 0) ? intval(input('limit')) : 10;

            $proxyid = input('proxyid'); //代理id
            $channelid = input('channelid'); //渠道id
            $nickname = input('nickname');//代理昵称
            $username = input('username');//代理账号

            $start = input('start');//时间
            if (!$start) {
                $start = date('Y-m-d');
            }

            $proxy = session("proxy");
            $data['proxyid'] = $proxy['code'];
            $data['time'] = $start;

            $channelList = [];
            if ($proxy['code'] == 1) {//管理员
                if ($channelid) {
                    $channelList = Db::name('proxy')->where(['type' => 2, 'code' => ['like',"%".$channelid."%"]])->field('code')->select();
                    $channelList = array_column($channelList, 'code');
                } else {
                    $channelList = Db::name('proxy')->where(['type' => 2])->field('code')->select();
                    $channelList = array_column($channelList, 'code');
                }
            } else {//渠道查自己
                $channelList[] = $proxy['code'];
            }

            //获取渠道团队信息

            $teamList = Db::name('teamlevel')->where(['parent_id'=> ['in', $channelList]])->field('proxy_id')->select();

            $teamList = array_column($teamList, 'proxy_id');
            //合并
            $teamList = array_merge($teamList, $channelList);

            $where = [];
            $where['code'] =['in', $teamList];
            if ($proxyid) {
                $where['code'] = $proxyid;
            }
            if ($username) {
                $where['username'] = ['like', "%".$username."%"];
            }
            if ($nickname) {
                $where['nickname'] = ['like', "%".$nickname."%"];
            }


            $count = Db::name('proxy')->where($where)->count();

            $data['count'] = $count;
            if (!$count) {
                return json($data);
            }
            $list = Db::name('proxy')
                ->where($where)
                ->field('code,username, nickname,percent,last_login')
                ->page($page, $limit)
                ->select();
            foreach ($list as &$v) {
                $v['time'] = $start;
                //获取对应渠道ID
                $max = Db::name('teamlevel')->where(['proxy_id' => $v['code']])->max('level') - 1;
                //最大level-1即渠道
                if ($max == 0) {//为0即自己
                    $v['channelid'] = $v['code'];
                } else {
                    $parent = Db::name('teamlevel')->where(['proxy_id' => $v['code'], 'level' => $max])->field('parent_id')->find();
                    $v['channelid'] = $parent['parent_id'];
                }

                //获取玩家充值,转出,代理盈利
                $v['recharge'] = Db::name('paytime')->where(['proxy_id' => $v['code'], 'addtime' => ['like', $start.'%'], 'typeid' => 0])->sum('totalfee');
                $v['withdraw'] = Db::name('paytime')->where(['proxy_id' => $v['code'], 'addtime' => ['like', $start.'%'], 'typeid' => 1])->sum('totalfee');
                $v['income']   = $v['recharge'] - $v['withdraw'];
                //获取代理提现
                $v['proxydraw'] = Db::name('checklog')->where(['proxy_id' => $v['code'], 'addtime' => ['like', $start.'%'], 'status' => 4])->sum('amount');
                //获取代理直属玩家税收
                $v['tax'] = Db::name('incomelog')->where(['proxy_id' => $v['code'], 'typeid' => 0, 'fxtype' => 1,'addtime' => ['like', $start.'%']])->sum('totaltax');
            }
            unset($v);
            $data['data'] = $list;

            $income = 0;
            if ($proxy['code'] != 1) {//渠道统计总盈利
                $recharge = Db::name('paytime')->where(['proxy_id' => ['in',$teamList], 'addtime' => ['like', $start.'%'], 'typeid' => 0])->sum('totalfee');
                $withdraw = Db::name('paytime')->where(['proxy_id' => ['in',$teamList], 'addtime' => ['like', $start.'%'], 'typeid' => 1])->sum('totalfee');
                $income = $recharge - $withdraw;
            }
            $data['income'] = $income;


            return json($data);
        } else {

            $this->assign('proxyid', session('proxy_id'));
            return $this->fetch();
        }
    }


    //玩家明细
    public function playerdetail()
    {
        if (Request::instance()->isAjax()) {
            $data = [
                'code' => 0,
                'msg' => '',
                'count' => 0,
                'data' => [],
                'proxyid' => 0
            ];
            $page  = (intval(input('page')) > 0) ? intval(input('page')) : 1;
            $limit = (intval(input('limit')) > 0) ? intval(input('limit')) : 10;

            $proxyid = input('proxyid'); //代理id
            $channelid = input('channelid'); //渠道id
            $userid = input('userid');//玩家ID

            $start = input('start');//时间
            if (!$start) {
                $start = date('Y-m-d');
            }

            $proxy = session("proxy");
            $data['proxyid'] = $proxy['code'];
            $channelList = [];
            if ($proxy['code'] == 1) {//管理员
                if ($channelid) {
                    $channelList = Db::name('proxy')->where(['type' => 2, 'code' => ['like',"%".$channelid."%"]])->field('code')->select();
                    $channelList = array_column($channelList, 'code');
                } else {
                    $channelList = Db::name('proxy')->where(['type' => 2])->field('code')->select();
                    $channelList = array_column($channelList, 'code');
                }
            } else {//渠道查自己
                $channelList[] = $proxy['code'];
            }

            //获取渠道团队信息

            $teamList = Db::name('teamlevel')->where(['parent_id'=> ['in', $channelList]])->field('proxy_id')->select();

            $teamList = array_column($teamList, 'proxy_id');
            //合并
            $teamList = array_merge($teamList, $channelList);

            $where = [];
            $where['proxy_id'] =['in', $teamList];
            if ($proxyid) {
                $where['proxy_id'] = $proxyid;
            }
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
                ->field('userid,nickname, proxy_id')
                ->page($page, $limit)
                ->select();
            foreach ($list as &$v) {
                $v['time'] = $start;
                //获取对应渠道ID
                $max = Db::name('teamlevel')->where(['proxy_id' => $v['proxy_id']])->max('level') - 1;
                //最大level-1即渠道
                if ($max == 0) {//为0即自己
                    $v['channelid'] = $v['proxy_id'];
                } else {
                    $parent = Db::name('teamlevel')->where(['proxy_id' => $v['proxy_id'], 'level' => $max])->field('parent_id')->find();
                    $v['channelid'] = $parent['parent_id'];
                }

                //获取玩家充值,转出
                $v['recharge'] = Db::name('paytime')->where(['userid' => $v['userid'], 'addtime' => ['like', $start.'%'], 'typeid' => 0])->sum('totalfee');
                $v['withdraw'] = Db::name('paytime')->where(['userid' => $v['userid'], 'addtime' => ['like', $start.'%'], 'typeid' => 1])->sum('totalfee');

                //获取代理直属玩家税收
                $v['tax'] = Db::name('playerorder')->where(['userid' => $v['userid'],'addtime' => ['like', $start.'%']])->sum('total_tax');
            }
            unset($v);
            $data['data'] = $list;
            return json($data);
        } else {
            $this->assign('proxyid', session('proxy_id'));
            return $this->fetch();
        }
    }
}
