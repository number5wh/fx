<?php

namespace app\admin\controller;

use app\admin\controller\Base;
use think\Db;
use think\captcha\Captcha;
use app\admin\model;
use think\Exception;
use think\Log;
use think\Request;

class Index extends Base {

    public function index() {
        if ($this->verify()) {
            $proxy = session("proxy");
            $proxyInfo = Db::name("proxy")->where("code",$proxy["code"])->find();
            $bind_mobile = $proxyInfo["bind_mobile"];
            if($bind_mobile=='' || empty($bind_mobile)){
                $bind_mobile = "false";
            }
            $this->assign("proxy",$proxy);
            $this->assign("bind_mobile",$bind_mobile);
            $this->assign('percent', $proxyInfo['percent']);
            return $this->fetch();
        } else {
            $this->redirect('login/index');
        }
    }

    public function main(){
        $message =Db::name("message")->order( 'id desc ')->select();

        $today=strtotime(date('Y-m-d 00:00:00'));

        $proxy_id =session("proxy_id");
        $proxy = session("proxy");
       // $now =
        $data['addtime'] = array('egt',$today);

        //获取所有后代代理及自己的信息
        $teamlevels = Db::name('teamlevel')->where('parent_id', session("proxy_id"))->select();
        $proxyList = array_column($teamlevels, 'proxy_id');
        array_unshift($proxyList, session('proxy_id'));

        //总注册
        $regtotal = Db::name("player")
            ->where(['proxy_id'=>['in', $proxyList]])
            ->count();


        //今日注册
        $regtoday = Db::name("player")
            ->where([
                'proxy_id'=>['in', $proxyList],
                'regtime' => ['egt', date('Y-m-d 00:00:00')]
            ])
            ->count();


        //昨日注册
        $regyestday = Db::name("player")
            ->where([
                'proxy_id'=>['in', $proxyList],
                'regtime' => [['egt', date('Y-m-d 00:00:00', strtotime('-1 day'))],['lt', date('Y-m-d 00:00:00')]]
            ])
            ->count();

        //总税收
        $totaltax = Db::name("incomelog")
            ->where([
                'proxy_id' => session('proxy_id'),
                'typeid'   => ['in', [0,4]]
            ])
            ->sum("totaltax");

        //总收益
        $allmoney = Db::name("incomelog")
            ->where(
                [
                    'proxy_id' => session('proxy_id'),
                    'typeid'   => ['in', [0,4]]
                ]
            )
            ->sum('changmoney');

        //昨日收益
        $today = strtotime(date('Y-m-d 00:00:00'));
        $yesterday = $today - 24*3600;

        $yesterdaytt = Db::name("incomelog")
            ->where(      [
                'proxy_id' => session('proxy_id'),
                'typeid'   => ['in', [0,4]],
                'createtime' => [['egt', $yesterday], ['lt', $today]]
            ])
            ->sum('changmoney');

        $todaytt = Db::name("incomelog")
            ->where(      [
                'proxy_id' => session('proxy_id'),
                'typeid'   => ['in', [0,4]],
                'createtime' => ['egt', $today]
            ])
            ->sum('changmoney');


        //领取红包按钮
        $getReward = 0;
        $reward = Db::name("dayreward")->where(['income' => ['elt', $yesterdaytt]])->order("income desc")->limit(1)->find();
        if (!$reward) {
            $getReward = 1; //未达标
        } else {
            if (Db::name('incomelog')->where([
                'proxy_id' => $proxy_id,
                'typeid'   => config('incomelog.day_reward'),
                'createday' => date('Ymd')
            ])->find()) {
                $getReward = 2;
            }
        }

        $this->assign('regtotal',$regtotal);
        $this->assign('regtoday',$regtoday);
        $this->assign("regzt",$regyestday);
        $this->assign("totaltax",$totaltax);
        $this->assign("zrsy",$yesterdaytt);
        $this->assign("jrsy",$todaytt);
        $this->assign("alltt",$allmoney);
        $this->assign('getreward', $getReward);
        $this->assign("list",$message);
        return $this->fetch();
    }

    public function notice(){
        return $this->fetch();
    }



    //按每日收益领取红包(昨日)
    private function handleReward()
    {
        $proxy_id = session("proxy_id");
        $proxy = session("proxy");
        $data = ['code' => 0, 'msg' => '', 'data' => []];
        //昨日收益
        $today = strtotime(date('Y-m-d 00:00:00'));
        $yesterday = $today - 24*3600;

        $yesterdaytt = Db::name("incomelog")
            ->where(      [
                'proxy_id' => session('proxy_id'),
                'typeid'   => ['in', [0,4]],
                'createtime' => [['egt', $yesterday], ['lt', $today]]
            ])
            ->sum('changmoney');
        //$todaytt = 700;
        $where = ['income' => ['elt', $yesterdaytt]];
        //获取可领取的奖励
        $reward = Db::name("dayreward")->where($where)->order("income desc")->limit(1)->find();
        if (!$reward) {
            $data['code'] = 1;
            $data['msg']  = '昨日收益不达标，不能领取红包奖励';
            return $data;
        }

        //判断今日是否已经领取过
        $where1 = [
            'proxy_id' => $proxy_id,
            'typeid'   => config('incomelog.day_reward'),
            'createday' => date('Ymd')
        ];
        if (Db::name('incomelog')->where($where1)->find()) {
            $data['code'] = 2;
            $data['msg']  = '今日已经领取过红包了哦';
            return $data;
        }
        $data['data'] = ['total' =>floor($yesterdaytt/100)*100,'reward' => $reward ];
        return $data;
    }


    //领取提示
    public function getRewardPre()
    {
        $return = $this->handleReward();
        if ($return['code'] == 0) {
            $return['msg'] = "您的昨日收益为{$return['data']['total']}元，可领取红包为{$return['data']['reward']['reward']}元";
        }

        return json($return);
    }

    //领取
    public function getReward()
    {
        $return = $this->handleReward();
        if ($return['code'] != 0) {
            return json($return);
        }
        $proxy_id = session("proxy_id");
        $proxy = session("proxy");
        $res = ['code' => 0,'msg' => '', 'data' => []];
        $reward = $return['data']['reward'];
        $today = $return['data']['total'];
        $descript = $proxy_id."昨日收益为{$today}元，达到{$reward['income']}元条件，领取金额{$reward['reward']}元";

        Db::startTrans();
        try {
            //记录到incomelog
            $insertLog = [
                'proxy_id' => $proxy_id,
                'typeid' => config('incomelog.day_reward'),
                'changmoney' => $reward['reward'],
                'createtime' => time(),
                'descript' => $descript,
                'createday' => date('Ymd')
            ];
            Db::name('incomelog')->insert($insertLog);

            //添加到用户余额
            Db::name('proxy')
                ->where("code",$proxy_id)
                ->update([
                    'balance' =>Db::raw('balance+'.$reward['reward']),
                    'historyin' => Db::raw('historyin+'.$reward['reward']),
                ]);
            Db::commit();
            $res['msg'] = '领取成功';
            return json($res);
        } catch (\Exception $e) {
            Log::write($e->getMessage(),"day_reward");
            Db::rollback();
            $res['code'] = 1;
            $res['msg']  = "领取失败，请稍后重试";
            return json($res);
        }

    }
    
    //获取红包明细
    public function rewardList()
    {
        $proxy_id = session("proxy_id");
        if (Request::instance()->isAjax()) {
            $page  = intval(input('page')) > 0 ? intval(input('page')) : 1;
            $limit = intval(input('limit')) > 0 ? intval(input('limit')) : 15;
            $proxy = input('proxy_id') ? trim(input('proxy_id')) : '';
            if ($proxy_id == 1) {//admin
                $where = [
                    'a.typeid'   => config('incomelog.day_reward')
                ];
                if ($proxy) {
                    $where['a.proxy_id'] = $proxy;
                }
            } else {//其他人
                $where = [
                    'a.proxy_id' => $proxy_id,
                    'a.typeid'   => config('incomelog.day_reward')
                ];
            }

            $count = Db::name('incomelog')->alias('a')->where($where)->count();
            $list     = Db::name('incomelog')
                ->alias('a')
                ->join('proxy b', 'a.proxy_id=b.code', 'left')
                ->field('a.id,a.proxy_id,a.createtime,a.changmoney,a.descript,b.username,b.nickname')
                ->where($where)
                ->order('a.createtime desc')
                ->page($page, $limit)
                ->select();

            foreach ($list as &$v) {
                $v['createtime'] = date('Y-m-d H:i:s', $v['createtime']);
            }
            unset($v);
            $data = ['code' => 0,'msg'=>'','data' => $list, 'count' => $count];
            return json($data);
        } else {
            $this->assign('proxyid', $proxy_id);
            return $this->fetch();
        }
    }
}
