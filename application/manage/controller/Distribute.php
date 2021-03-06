<?php

namespace app\manage\controller;

use think\Db;
use app\manage\controller\Base;
use think\session;

class Distribute extends Base
{
    public function _initialize()
    {
        $roleid = session("RoleId");
        if ($roleid != 1) {
            $this->error('您没有权限', 'index/index');
        }
    }

    /**
     * 推广统计
     */
    public function index()
    {
        $agentid  = input("agentId");
        $regstart = input("startTime");
        $regend   = input("endTime");
        $month    = input("month");
        $proxy    = session("proxy");


        $where = [];
        $wheredate = [];
        if ($agentid) {
            $where['a.proxy_id'] = $agentid;
        }

        if (!empty($regstart)) {
            if (!empty($regend)) {
                $where['a.day']   = array(array('egt', $regstart), array('elt', $regend));
                $wheredate['day'] = array(array('egt', $regstart), array('elt', $regend));
            } else {
                $where['a.day']   = array('egt', $regstart);
                $wheredate['day'] = array('egt', $regstart);
            }
        }


        if (!$month) {
            $list = Db::name('spreadsum')
                ->alias('a')
                ->field('a.day time,a.proxy_id,sum(a.paynum) paynum, sum(a.bindnum) bindnum,sum(a.regnum) regnum,sum(a.totaltax) totaltax,sum(a.checknum) checknum,b.nickname')
                ->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
                ->where($where)
                ->order('a.day desc')
                ->group('a.day, a.proxy_id')
                ->paginate(10, false, ['query' => request()->param()])
                ->each(function ($item, $key) {
                    $item['totaltax'] = round($item['totaltax'], 3);
                    return $item;
                });
        } else {
            $list = Db::name('spreadsum')
                ->alias('a')
                ->field('a.week time, a.weekstart,a.weekend,a.proxy_id,sum(a.paynum) paynum, sum(a.bindnum) bindnum,sum(a.regnum) regnum,sum(a.totaltax) totaltax,sum(a.checknum) checknum,b.nickname')
                ->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
                ->where($where)
                ->order('a.week desc')
                ->group('a.week, a.proxy_id,a.weekstart,a.weekend')
                ->paginate(10, false, ['query' => request()->param()])
                ->each(function ($item, $key) {
                    $item['totaltax'] = round($item['totaltax'], 3);
                    $item['time'] = $item['weekstart'].'~'.$item['weekend'];
                    return $item;
                });
        }


        $sumdata = Db::name('spreadsum')
            ->alias('a')
            ->field('sum(a.regnum) regnum,sum(a.totaltax) totaltax')
            ->where($where)
            ->find();

        if (!$sumdata['regnum']) {
            $sumdata['regnum'] = 0;
        }
        if (!$sumdata['totaltax']) {
            $sumdata['totaltax'] = 0;
        }
        $sumdata['totaltax'] = round($sumdata['totaltax'], 3);

        $this->assign('sumdata', $sumdata);
        $this->assign('list', $list);
        $this->assign("agentid", $agentid);
        $this->assign("regstart", $regstart);
        $this->assign("regend", $regend);
        $this->assign("proxy", $proxy);
        $this->assign("month", $month);
        return $this->fetch('distribute/index');
    }

    /*
     * 收入统计
     */
//    public function incomeSum()
//    {
//
//        $agentid   = input("agentId");
//        $starttime = input("startTime");
//        $endtime   = input("endTime");
//        $month     = input("month");
//
//
//        $where    = array();
//        $strwhere = " 1=1 ";
//        if (!empty($agentid)) {
//            $where['proxy_id'] = $agentid;
//            $strwhere          .= " and proxy_id='" . $agentid . "'";
//        }
//
//
//        if (!empty($starttime)) {
//            if (!empty($endtime)) {
//                $where['addtime'] = array(array('gt', $starttime), array('lt', $endtime));
//                $strwhere         .= " and addtime>'" . $starttime . "' and addtime<='" . $endtime . "'";
//            } else {
//                $where['addtime'] = array('gt', $starttime);
//                $strwhere         .= " and addtime>'" . $starttime . "'";
//            }
//        }
//
//        $PlayerOrder = Model("PlayerOrder");
//        if (empty($month)) {
//            $listdata = $PlayerOrder
//                ->field(" date_format(`addtime`,'%Y-%m-%d') dt,sum(total_tax) as totaltax")
//                ->where($where)
//                ->whereor($strwhere)
//                ->group("date_format(`addtime`,'%Y-%m-%d')  ")
//                ->order("dt desc")
//                ->paginate()
//                ->each(function ($item, $key) {
//                    $select_id = session("proxy_id");
//                    $child     = Db::name("playerorder")
//                        ->field("date_format(`addtime`,'%Y-%m-%d') as cdate,sum(total_tax) as total, a.proxy_id,b.nickname,b.percent")
//                        ->alias('a')->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
//                        ->where(" date_format(`addtime`,'%Y-%m-%d')='" . $item['dt'] . "' and a.parent_id='" . $select_id . "' ")
//                        ->group(" a.proxy_id")->select();
//
//
//                    $child0 = Db::name("playerorder")
//                        ->field("date_format(`addtime`,'%Y-%m-%d') as cdate,sum(total_tax) as total, a.proxy_id,b.nickname,b.percent")
//                        ->alias('a')->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
//                        ->where(" date_format(`addtime`,'%Y-%m-%d')='" . $item['dt'] . "' and a.proxy_id='" . $select_id . "' ")
//                        ->group(" a.proxy_id")->select();
//
//                    $child         = array_merge($child0, $child);
//                    $item['child'] = $child;
//                    $item["rows"]  = count($child) + 1;
//                    $totaltax      = 0;
//                    $mytax         = 0;
//                    foreach ($child as $key => $value) {
//                        $totaltax = $totaltax + ($value["total"] * $value["percent"]) / 100;
//                        if ($value['proxy_id'] == $select_id) {
//                            $mytax = $mytax + ($value["total"] * $value["percent"]) / 100;
//                        } else {
//                            $proxy     = session("proxy");
//                            $mypercent = $proxy['percent'] - $value["percent"];
//                            $mytax     = $mytax + ($value["total"] * $mypercent / 100);
//                        }
//                    }
//                    $item["mytax"] = $mytax;
//                    $item["ttax"]  = $totaltax;
//
//                });
//        } else {
//
//            $listdata = $PlayerOrder
//                ->field(" date_format(`addtime`,'%Y-%m') dt,sum(total_tax) as totaltax")
//                ->where($where)
//                ->whereor($strwhere)
//                ->group("date_format(`addtime`,'%Y-%m')  ")
//                ->order("dt desc")
//                ->paginate()
//                ->each(function ($item, $key) {
//                    $child = Db::name("playerorder")
//                        ->field("date_format(`addtime`,'%Y-%m') as cdate,sum(total_tax) as total, a.proxy_id,b.nickname,b.percent")
//                        ->alias('a')->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
//                        ->where(" date_format(`addtime`,'%Y-%m')='" . $item['dt'] . "'")
//                        ->group(" a.proxy_id")->select();
//
//
//                    $child0 = Db::name("playerorder")
//                        ->field("date_format(`addtime`,'%Y-%m') as cdate,sum(total_tax) as total, a.proxy_id,b.nickname,b.percent")
//                        ->alias('a')->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
//                        ->where(" date_format(`addtime`,'%Y-%m')='" . $item['dt'] . "'")
//                        ->group(" a.proxy_id")->select();
//
//                    $child         = array_merge($child0, $child);
//                    $item['child'] = $child;
//                    $item["rows"]  = count($child) + 1;
//                    $totaltax      = 0;
//                    $mytax         = 0;
//                    foreach ($child as $key => $value) {
//                        $totaltax = $totaltax + ($value["total"] * $value["percent"]) / 100;
//                        if ($value['proxy_id'] == 0) {// $select_id) {
//                            $mytax = $mytax + ($value["total"] * $value["percent"]) / 100;
//                        } else {
//                            $proxy     = session("proxy");
//                            $mypercent = $proxy['percent'] - $value["percent"];
//                            $mytax     = $mytax + ($value["total"] * $mypercent / 100);
//                        }
//                    }
//                    $item["mytax"] = $mytax;
//                    $item["ttax"]  = $totaltax;
//
//                });
//
//
//        }
//
//        // print_r($listdata);
//
//
//        $this->assign("agentid", $agentid);
//        $this->assign("starttime", $starttime);
//        $this->assign("endtime", $endtime);
//        $this->assign('list', $listdata);
//        return $this->fetch('distribute/incomeSum');
//    }


    /*
     * 收入明细
     */
    public function incomeDetail()
    {
        $gamerId  = input("gamerId");
        $gameType = input("gameType");
        $startime = input("startTime");
        $endTime  = input("endTime");
        $where    = array();
        $strwhere = " 1=1 ";
        if (!empty($gamerId)) {
            $where["a.userid"] = $gamerId;
            $strwhere          .= " and a.userid=" . $gamerId;
        }

        if (!empty($gameType)) {
            $where["a.gameid"] = $gameType;
            $strwhere          .= " and a.gameid=" . $gameType;
        }

        if (!empty($startime)) {
            if (!empty($endTime)) {
                $where['a.addtime'] = array(array('gt', $startime), array('lt', $endTime));
                $strwhere           .= " and a.addtime>'" . $startime . "' and a.addtime<='" . $endTime . "'";
            } else {
                $where['a.addtime'] = array('gt', $startime);
                $strwhere           .= " and a.addtime>'" . $startime . "'";
            }
        }

        $list = Db::name("playerorder")->alias('a')
            ->field("a.proxy_id,a.userid,a.gameid,a.game,a.total_tax,b.percent,a.createtime")
            ->join(" proxy b ", " a.proxy_id=b.code", "LEFT")
            ->where($where)
            ->whereor($strwhere)
            ->paginate(10, false, ['query' => request()->param()]);

        $this->assign("list", $list);
        $this->assign("gamerid", $gamerId);
        $this->assign("starttime", $startime);
        $this->assign("endTime", $endTime);
        $this->assign("gameType", $gameType);
        return $this->fetch('distribute/incomedetail');
    }


    public function playerDetail()
    {

        $gamerId  = input("gamerId");
        $startime = input("startTime");
        $endTime  = input("endTime");
        $proxyId  = input('proxy_id');

        $startime1 = $startime ? $startime . " 00:00:00" : '';
        $endTime1  = $endTime ? $endTime . " 23:59:59" : '';
        $where     = array();
        if (!empty($gamerId)) {
            $where["a.userid"] = $gamerId;
        }
        if (!empty($proxyId)) {
            $where['a.proxy_id'] = $proxyId;
        }

        if (!empty($startime1)) {
            $startime1 = str_replace("-", "/", $startime1);
            $endTime1  = str_replace("-", "/", $endTime1);
            if (!empty($endTime)) {
                $where['a.regtime'] = array(array('gt', $startime1), array('lt', $endTime1));
            } else {
                $where['a.regtime'] = array('gt', $startime1);
            }
        }

        if ($where) {
            $list = Db::name("playerall")->alias('a')
                ->field(" a.proxy_id,a.userid,b.nickname as proxyname,a.nickname,a.ismobile,a.regtime,a.addtime")
                ->join(" proxy b ", " a.proxy_id=b.code", "LEFT")
                ->where($where)
                ->order("a.regtime desc")
                ->paginate(10, false, ['query' => request()->param()]);
        } else {
            $list = Db::name("playerall")->alias('a')
                ->field(" a.proxy_id,a.userid,b.nickname as proxyname,a.nickname,a.ismobile,a.regtime,a.addtime")
                ->join(" proxy b ", " a.proxy_id=b.code", "LEFT")
                ->order("a.regtime desc")
                ->paginate(10, false, ['query' => request()->param()]);
        }


        $this->assign("list", $list);
        $this->assign("gamerid", $gamerId);
        $this->assign("proxyid", $proxyId);
        $this->assign("starttime", $startime);
        $this->assign("endTime", $endTime);
        return $this->fetch();
    }


    //修改玩家的代理ID
    public function setPlayerProxyId()
    {
        $userId  = input("userid");
        $proxyId = input("proxyid");
        if (!$userId || !$proxyId || !is_numeric($userId)) {
            return $this->showmsg(0, "参数有误", '', '', null);
        }
        $distributeModel = new \app\manage\model\Distribute();
        $ret             = $distributeModel->setProxyId($proxyId, $userId);
        if ($ret->data == "True") {
            Db::name('thirdplayerall')->where('userid', $userId)->update(['parentid' => $proxyId]);
            Db::name('playerall')->where('userid', $userId)->update(['proxy_id' => $proxyId]);
            return $this->showmsg(1, "更新成功", '', '', null);
        } else {
            return $this->showmsg(0, "更新失败", '', '', null);
        }

    }


}