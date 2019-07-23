<?php

namespace app\admin\controller;

use redis\Redis;
use think\Db;
use app\admin\controller\Base;
use think\session;

class Distribute extends Base
{
    /**
     * 收入统计
     */
    public function index()
    {
        $agentid  = input("agentId");
        $regstart = input("startTime");
        $regend   = input("endTime");
        $month    = input("month");
        $proxy    = session("proxy");


        $where = $wheresum = array();
        $key   = 'zsteam_children_' . $proxy["code"];
        $team  = Redis::getInstance()->get($key);
        if (!$team) {
            $team = Db::name("teamlevel")->where(["parent_id" => $proxy["code"], "level" => 1])->select();
            $team = array_column($team, 'proxy_id');
            //$team[] = $proxy["code"];
            Redis::getInstance()->set($key, $team);
        }


        if ($agentid) {
            if (!in_array($agentid, $team)) {
                $where['a.proxy_id']  = 'false';
                $wheresum['proxy_id'] = 'false';
            } else {
                $where['a.proxy_id']  = $agentid;
                $wheresum['proxy_id'] = $agentid;
            }

        } else {
            $where['a.proxy_id']  = ['in', $team];
            $wheresum['proxy_id'] = $proxy["code"];
        }

        $wheredate = [];
        $whereeach = [];
        if (!empty($regstart)) {
            if (!empty($regend)) {
                $where['a.day']       = array(array('egt', $regstart), array('elt', $regend));
                $wheresum['day']      = array(array('egt', $regstart), array('elt', $regend));
                $wheredate['day']     = array(array('egt', $regstart), array('elt', $regend));
                $whereeach['addtime'] = array(array('egt', $regstart), array('elt', $regend));
            } else {
                $where['a.day']       = array('egt', $regstart);
                $wheresum['day']      = array('egt', $regstart);
                $wheredate['day']     = array('egt', $regstart);
                $whereeach['addtime'] = array('egt', $regstart);
            }
        } else {
            $regstart = $regend = date('Ymd');
            $where['a.day']       = array(array('egt', $regstart), array('elt', $regend));
            $wheresum['day']      = array(array('egt', $regstart), array('elt', $regend));
            $wheredate['day']     = array(array('egt', $regstart), array('elt', $regend));
            $whereeach['addtime'] = array(array('egt', $regstart), array('elt', $regend));
        }


        $key2 = 'myteam_'.$proxy['code'];
        $teamData = Redis::getInstance()->get($key2);
        if (!$teamData) {
            $myPercent = Db::name('proxy')->where(['code' => $proxy['code']])->field('percent')->find();
            $myPercent = $myPercent['percent'];
            $myTeam = Db::name('proxy')->where(['code' => ['in', $team]])->field('code, percent')->select();

            $teamData = [
                'mypercent' => $myPercent,
                'myteam'    => $myTeam
            ];
            Redis::getInstance()->set($key2, $teamData);
        }


        if (!$month) {
            $list = Db::name('spreadsum')
                ->alias('a')
                ->field('a.day time,a.proxy_id,sum(a.paynum) paynum, sum(a.bindnum) bindnum,sum(a.historytax) totaltax, 
                sum(a.checknum) checknum,b.nickname')
                ->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
                ->where($where)
                ->order('a.day desc')
                ->group('a.day, a.proxy_id')
                ->paginate(10, false, ['query' => request()->param()])
                ->each(function ($item, $key) use ($teamData) {

                    $item['totaltax'] = round($item['totaltax'], 3);
                    $percent          = 0;
                    foreach ($teamData['myteam'] as $v) {
                        if ($v['code'] == $item['proxy_id']) {
                            $percent = $v['percent'];
                            break;
                        }
                    }
                    $item['percent']  = $percent;
                    $item['getpercent'] = $teamData['mypercent'] - $percent;
                    $item['getmoney'] = round($item['totaltax'] * ($teamData['mypercent'] - $percent) / 100, 3);
                    return $item;
                });
        } else {
            $list = Db::name('spreadsum')
                ->alias('a')
                ->field('a.week time, a.weekstart,a.weekend,a.proxy_id,sum(a.paynum) paynum, sum(a.bindnum) bindnum,sum(a.historytax) totaltax,
                sum(a.checknum) checknum,b.nickname')
                ->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
                ->where($where)
                ->order('a.week desc')
                ->group('a.week, a.proxy_id,a.weekstart,a.weekend')
                ->paginate(10, false, ['query' => request()->param()])
                ->each(function ($item, $key)use ($teamData) {
                    $item['totaltax'] = round($item['totaltax'], 3);
                    $percent          = 0;
                    foreach ($teamData['myteam'] as $v) {
                        if ($v['code'] == $item['proxy_id']) {
                            $percent = $v['percent'];
                            break;
                        }
                    }
                    $item['percent']  = $percent;
                    $item['getpercent'] = $teamData['mypercent'] - $percent;

                    $item['getmoney'] = round($item['totaltax'] * ($teamData['mypercent'] - $percent) / 100, 3);
                    return $item;
                });
        }

        $sumdata = Db::name('spreadsum')
            ->alias('a')
            ->field('sum(a.regnum) regnum,sum(a.historytax) totaltax, sum(totalin) totalin')
            ->where($wheresum)
            ->find();

        if (!$sumdata['regnum']) {
            $sumdata['regnum'] = 0;
        }
        if (!$sumdata['totaltax']) {
            $sumdata['totaltax'] = 0;
        }

        $sumdata['totalin'] = round($sumdata['totalin'], 3);
        $this->assign('list', $list);
        $this->assign('sumdata', $sumdata);
        $this->assign("agentid", $agentid);
        $this->assign("regstart", $regstart);
        $this->assign("regend", $regend);
        $this->assign("proxy", $proxy);
        $this->assign("month", $month);
        return $this->fetch('distribute/index');
    }

    /*
     * 收入明细
     */
    public function incomeDetail()
    {
        $gamerId  = input("gamerId");
        $gameType = input("gameType");
        $startime = input("startTime");
        $endTime  = input("endTime");

        $searchProxy = input('proxyId');

        $proxy_id = session("proxy_id");
        $proxy    = session("proxy");

        if ($proxy_id != 1) {
            $subListId = [];
            $subList   = Db::table('teamlevel')->where(['parent_id' => $proxy['code']])->select();
            if ($subList) {
                $subListId = array_column($subList, 'proxy_id');
            }
            array_unshift($subListId, $proxy['code']);
        }


//        $strwhere = ' 1=1';
        $where   = array();
        $whereOr = array();

        if ($searchProxy) {
            if ($proxy_id == 1) {
                $where['a.proxy_id'] = $searchProxy;
            } else {
                if (!in_array($searchProxy, $subListId)) {
                    $where['a.proxy_id'] = 'false';
                } else {
                    $where['a.proxy_id'] = $searchProxy;
                }
            }
        } else {
            if ($proxy_id == 1) {

            } else {
                $where['a.proxy_id'] = ['in', $subListId];
            }
        }

        if (!empty($gamerId)) {
            $where["a.userid"] = $gamerId;
        }

        if (!empty($gameType)) {
            $where["a.gameid"] = $gameType;
        }


//        $where["a.proxy_id"] = $proxy_id;
        if (!empty($startime)) {
            if (!empty($endTime)) {
                $where['a.addtime'] = array(array('egt', $startime), array('elt', str_replace('-', '/', $endTime)));
            } else {
                $where['a.addtime'] = array('egt', str_replace('-', '/', $startime));
            }
        } else {
            $startime = $endTime = date('Y-m-d');
            $where['a.addtime'] = array(array('egt', $startime), array('elt', str_replace('-', '/', $endTime)));
        }


        $list = Db::name("playerorder")->alias('a')
            ->field("a.proxy_id,a.userid,a.gameid,a.game,a.total_tax,b.percent,a.createtime")
            ->join(" proxy b ", " a.proxy_id=b.code", "LEFT")
            ->where($where)
            ->order("a.createtime desc")
            ->paginate(15, false, ['query' => request()->param()]);

        $sumdata = Db::name("playerorder")->alias('a')
            ->field("sum(total_tax) totaltax")
            ->where($where)
            ->find();



        $this->assign("list", $list);
        $this->assign("sumdata", round($sumdata['totaltax'], 2));
        $this->assign("gamerid", $gamerId);
        $this->assign("proxy", $proxy);
        $this->assign('proxyid', $searchProxy);
        $this->assign("starttime", $startime);
        $this->assign("endTime", $endTime);
        $this->assign("gameType", $gameType);
        $this->assign("gametypeList", config('gametype'));
        return $this->fetch('distribute/incomedetail');
    }


    public function playerDetail()
    {

        $gamerId  = input("gamerId");
        $code     = input("code");
        $startime = input("startTime");
        $endTime  = input("endTime");


        $proxy_id = session("proxy_id");
        $proxy    = session("proxy");

        $startime1 = $startime . " 00:00:00";
        $endTime1  = $endTime . " 23:59:59";

        $where = array();

        //管理员查全部
        if (!empty($gamerId)) {
            $where["a.userid"] = $gamerId;
        }

        if ($proxy_id != 1) {
            $where["a.parent_id"] = $proxy_id;
        }

        if ($code) {
            $where['a.proxy_id'] = $code;
        }

        if (!empty($startime)) {
            if (!empty($endTime)) {
                $where['a.regtime'] = array(array('gt', $startime1), array('lt', $endTime1));
            } else {
                $where['a.regtime'] = array('gt', $startime1);
            }
        } else {
            $startime = $endTime = date('Y-m-d');
            $startime1 = $startime . " 00:00:00";
            $endTime1  = $endTime . " 23:59:59";
            $where['a.regtime'] = array(array('gt', $startime1), array('lt', $endTime1));
        }


        $list = Db::name("player")->alias('a')
            ->field(" a.proxy_id,a.userid,a.proxy_name,a.nickname,a.ismobile,a.regtime,a.addtime")
            ->join(" proxy b ", " a.proxy_id=b.code", "LEFT")
            ->where($where)
            ->order("a.regtime desc")
            ->paginate('15', false, ['query' => request()->param()])
            ->each(function ($item, $key) {
                $tax              = Db::name('playerorder')
                    ->where(['userid' => $item['userid'], 'proxy_id' => $item['proxy_id']])
                    ->sum('total_tax');
                $item['totaltax'] = $tax;
                return $item;
            });

        $this->assign("list", $list);
        $this->assign("gamerid", $gamerId);
        $this->assign("code", $code);
        $this->assign("proxy", $proxy);
        $this->assign("starttime", $startime);
        $this->assign("endTime", $endTime);
        return $this->fetch();
    }


    public function OrderDetail()
    {

        $gamerId  = input("gamerId");
        $startime = input("startTime");
        $endTime  = input("endTime");

        $proxy_id = session("proxy_id");
        $proxy    = session("proxy");

        $startime1 = $startime . " 00:00:00";
        $endTime1  = $endTime . " 23:59:59";
        $where     = array();
        $strwhere  = "a.parent_id='" . $proxy_id . "'";
        if (!empty($gamerId)) {
            $where["a.userid"] = $gamerId;
            $strwhere          .= " and a.userid='{$gamerId}'";
        }

        $where["a.proxy_id"] = $proxy_id;
        if (!empty($startime1)) {
            $startime1 = str_replace("-", "/", $startime1);
            $endTime1  = str_replace("-", "/", $endTime1);
            if (!empty($endTime)) {
                $where['a.createtime'] = array(array('gt', $startime1), array('lt', $endTime1));
                $strwhere              .= " and a.createtime>'" . $startime1 . "' and a.createtime<'" . $endTime1 . "'";
            } else {
                $where['a.createtime'] = array('gt', $startime1);
                $strwhere              .= " and a.createtime>'" . $startime1 . "'";
            }
        }

        $list = Db::name("playerorder")->alias('a')
            ->field(" a.proxy_id,a.userid,b.nickname,a.game,a.total_tax,a.createtime,a.addtime")
            ->join(" proxy b ", " a.proxy_id=b.code", "LEFT")
            ->where($where)
            ->whereor($strwhere)->order("a.createtime desc")
            ->paginate(10, false, ['query' => request()->param()]);

        $this->assign("list", $list);
        $this->assign("gamerid", $gamerId);
        $this->assign("proxy", $proxy);
        $this->assign("starttime", $startime);
        $this->assign("endTime", $endTime);
        return $this->fetch();
    }


    //玩家充值明细
    public function paydetail()
    {

        if ($this->request->isAjax()) {
            $data   = [
                'code'  => 0,
                'msg'   => '',
                'count' => 0,
                'data'  => []
            ];
            $where  = [];
            $roleid = $this->request->get('roleid') ? trim($this->request->get('roleid')) : '';
            $start  = $this->request->get('start') ? $this->request->get('start') : '';
            $end    = $this->request->get('end') ? $this->request->get('end') : '';
            $page   = $this->request->get('page') ? intval($this->request->get('page')) : 1;
            $limit  = $this->request->get('limit') ? intval($this->request->get('limit')) : 10;

            $where['proxy_id'] = session('proxy_id');
            $where['typeid']   = 0;
            if ($roleid) {
                $where['userid'] = $roleid;
            }
            if ($start) {
                if ($end) {
                    $where['addtime'] = [['egt', $start . ' 00:00:00'], ['elt', $end . ' 23:59:59']];
                } else {
                    $where['addtime'] = ['egt', $start . ' 00:00:00'];
                }
            }


            $count = Db::name('paytime')->where($where)->count();
            $res   = [];
            if ($count > 0) {
                $res = Db::name('paytime')->where($where)->page($page, $limit)->select();
            }
            $data['count'] = $count;
            $data['data']  = $res;
            return json($data);
        }

        return $this->fetch();
    }
}