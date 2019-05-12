<?php

namespace app\admin\controller;

use think\Db;
use app\admin\controller\Base;
use think\session;

class Distribute extends Base
{
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

        $where    = array();
        if ($agentid) {
            $where['a.proxy_id']  = $agentid;
            $where['a.parent_id'] = $proxy['code'];
        } else {
            $where['a.parent_id'] = $proxy['code'];
        }

        if (!empty($regstart)) {
            if (!empty($regend)) {
                $where['a.day'] = array(array('egt', $regstart), array('elt', $regend));
            } else {
                $where['a.day'] = array('egt', $regstart);
            }
        }

        if (!$month) {
            $list = Db::name('spreadsum')
                ->alias('a')
                ->field('a.day time,a.parent_id,sum(a.paynum) paynum, sum(a.bindnum) bindnum,sum(a.regnum) regnum,sum(a.totaltax) totaltax,sum(a.checknum) checknum,b.nickname')
                ->join('proxy b',' a.parent_id=b.code ',"LEFT")
                ->where($where)
                ->order('a.day desc')
                ->group('a.day, a.parent_id')
                ->paginate(10)
                ->each(function ($item, $key) {
                    $children = Db::name('spreadsum')
                        ->alias('c')
                        ->field('c.day time,c.proxy_id,c.paynum,c.bindnum,c.regnum,c.totaltax,c.checknum,d.nickname')
                        ->join('proxy d',' c.proxy_id=d.code ',"LEFT")
                        ->where([
                            'c.day' => $item['time'],
                            'c.parent_id' => $item['parent_id']
                        ])
                        ->select();

                    $item['children'] = $children;
                    $item["rows"] =count($children)+1;
                    return $item;
                });
        } else {
            $list = Db::name('spreadsum')
                ->alias('a')
                ->field('a.month time,a.parent_id,sum(a.paynum) paynum, sum(a.bindnum) bindnum,sum(a.regnum) regnum,sum(a.totaltax) totaltax,sum(a.checknum) checknum,b.nickname')
                ->join('proxy b',' a.parent_id=b.code ',"LEFT")
                ->where($where)
                ->order('a.month desc')
                ->group('a.month, a.parent_id')
                ->paginate(10)
                ->each(function ($item, $key) {
                    $children = Db::name('spreadsum')
                        ->alias('c')
                        ->field('c.month time,c.proxy_id,sum(c.paynum) paynum,sum(c.bindnum) bindnum,sum(c.regnum) regnum,sum(c.totaltax) totaltax,sum(c.checknum) checknum,d.nickname')
                        ->join('proxy d',' c.proxy_id=d.code ',"LEFT")
                        ->where([
                            'c.month' => $item['time'],
                            'c.parent_id' => $item['parent_id']
                        ])
                        ->group('c.month,c.proxy_id')
                        ->select();

                    $item['children'] = $children;
                    $item["rows"] =count($children)+1;
                    return $item;
                });
        }

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
    public function incomeSum()
    {

        $agentid   = input("agentId");
        $starttime = input("startTime");
        $endtime   = input("endTime");
        $month     = input("month");
        $proxy     = session("proxy");


        $where    = array();
        $strwhere = " 1=1 ";
        if (!empty($agentid)) {
            $where['proxy_id']  = $agentid;
            $where['parent_id'] = $proxy["code"];
            $strwhere           .= " and proxy_id='" . $agentid . "'";
        } else {
            $where["proxy_id"] = $proxy["code"];
            $strwhere          .= " and parent_id='" . $proxy["code"] . "'";
        }


        $PlayerOrder = Model("PlayerOrder");
        if (empty($month)) {

            if (!empty($starttime)) {
                if (!empty($endtime)) {
                    $where['addtime'] = array(array('egt', $starttime), array('elt', $endtime));
                    $strwhere         .= " and addtime>='" . $starttime . "' and addtime<='" . $endtime . "'";
                } else {
                    $where['addtime'] = array('egt', $starttime);
                    $strwhere         .= " and addtime>='" . $starttime . "'";
                }
            }
            $listdata = $PlayerOrder
                ->field(" date_format(`addtime`,'%Y-%m-%d') dt,sum(total_tax) as totaltax")
                ->where($where)
                ->whereor($strwhere)
                ->group("date_format(`addtime`,'%Y-%m-%d')  ")
                ->order("dt desc")
                ->paginate(20)
                ->each(function ($item, $key) {
                    $select_id = session("proxy_id");
                    $child     = Db::name("playerorder")
                        ->field("date_format(`addtime`,'%Y-%m-%d') as cdate,sum(total_tax) as total, a.proxy_id,b.nickname,b.percent")
                        ->alias('a')->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
                        ->where(" date_format(`addtime`,'%Y-%m-%d')='" . $item['dt'] . "' and a.parent_id='" . $select_id . "' ")
                        ->group(" a.proxy_id")->select();


                    $child0 = Db::name("playerorder")
                        ->field("date_format(`addtime`,'%Y-%m-%d') as cdate,sum(total_tax) as total, a.proxy_id,b.nickname,b.percent")
                        ->alias('a')->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
                        ->where(" date_format(`addtime`,'%Y-%m-%d')='" . $item['dt'] . "' and a.proxy_id='" . $select_id . "' ")
                        ->group(" a.proxy_id")->select();

                    $child         = array_merge($child0, $child);
                    $item['child'] = $child;
                    $item["rows"]  = count($child) + 1;
                    $totaltax      = 0;
                    $mytax         = 0;
                    foreach ($child as $key => $value) {
                        if ($value['proxy_id'] == $select_id) {
                            $mytax = $mytax + ($value["total"] * $value["percent"]) / 100;
                        } else {
                            $proxy     = session("proxy");
                            $mypercent = $proxy['percent'] - $value["percent"];
                            $mytax     = $mytax + ($value["total"] * $mypercent / 100);
                            $totaltax  = $totaltax + ($value["total"] * $value["percent"]) / 100;
                        }
                    }
                    $item["mytax"] = $mytax;
                    $item["ttax"]  = $totaltax;

                });
        } else {
//按月
//                $listdata = $PlayerOrder
//                    ->field(" date_format(`addtime`,'%Y-%m') dt,sum(total_tax) as totaltax")
//                    ->where($where)
//                    ->whereor($strwhere)
//                    ->group("date_format(`addtime`,'%Y-%m')  ")
//                    ->order("dt desc")
//                    ->paginate(20)
//                    ->each(function ($item, $key) {
//                        $select_id = session("proxy_id");
//                        $child = Db::name("playerorder")
//                            ->field("date_format(`addtime`,'%Y-%m') as cdate,sum(total_tax) as total, a.proxy_id,b.nickname,b.percent")
//                            ->alias('a')->join('proxy b',' a.proxy_id=b.code ',"LEFT")
//                            ->where(" date_format(`addtime`,'%Y-%m')='".$item['dt']."' and a.parent_id='".$select_id."' ")
//                            ->group(" a.proxy_id")->select();
//
//
//                        $child0 = Db::name("playerorder")
//                            ->field("date_format(`addtime`,'%Y-%m') as cdate,sum(total_tax) as total, a.proxy_id,b.nickname,b.percent")
//                            ->alias('a')->join('proxy b',' a.proxy_id=b.code ',"LEFT")
//                            ->where(" date_format(`addtime`,'%Y-%m')='".$item['dt']."' and a.proxy_id='".$select_id."' ")
//                            ->group(" a.proxy_id")->select();
//
//                        $child=array_merge($child0,$child);
//                        $item['child'] = $child;
//                        $item["rows"] =count($child)+1;
//                        $totaltax =0;
//                        $mytax =0;
//                        foreach ($child as $key=>$value){
//                            $totaltax =$totaltax+($value["total"]*$value["percent"])/100;
//                            if($value['proxy_id']==$select_id) {
//                                $mytax = $mytax + ($value["total"] * $value["percent"]) / 100;
//                            }
//                            else
//                            {
//                                $proxy = session("proxy");
//                                $mypercent =$proxy['percent']-$value["percent"];
//                                $mytax = $mytax + ($value["total"] * $mypercent / 100);
//                            }
//                        }
//                        $item["mytax"] =$mytax;
//                        $item["ttax"] =$totaltax;
//
//                    });


            //按周
            $listdata = $PlayerOrder
                ->field(" week as dt,sum(total_tax) as totaltax")
                ->where($where)
                ->whereor($strwhere)
                ->group("week")
                ->order("week desc")
                ->paginate(20)
                ->each(function ($item, $key) {
                    $select_id = session("proxy_id");
                    $child     = Db::name("playerorder")
                        ->field("week as cdate,sum(total_tax) as total, a.weekstart,a.weekend, a.proxy_id,b.nickname,b.percent")
                        ->alias('a')->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
                        ->where(" week='" . $item['dt'] . "' and a.parent_id='" . $select_id . "' ")
                        ->group(" a.proxy_id")->select();


                    $child0 = Db::name("playerorder")
                        ->field("week as cdate,sum(total_tax) as total, a.weekstart,a.weekend, a.proxy_id,b.nickname,b.percent")
                        ->alias('a')->join('proxy b', ' a.proxy_id=b.code ', "LEFT")
                        ->where(" week='" . $item['dt'] . "' and a.proxy_id='" . $select_id . "' ")
                        ->group(" a.proxy_id")->select();

                    $child         = array_merge($child0, $child);
                    $item['child'] = $child;
                    $item["rows"]  = count($child) + 1;
                    $totaltax      = 0;
                    $mytax         = 0;
                    foreach ($child as $key => $value) {
                        $totaltax = $totaltax + ($value["total"] * $value["percent"]) / 100;
                        if ($value['proxy_id'] == $select_id) {
                            $mytax = $mytax + ($value["total"] * $value["percent"]) / 100;
                        } else {
                            $proxy     = session("proxy");
                            $mypercent = $proxy['percent'] - $value["percent"];
                            $mytax     = $mytax + ($value["total"] * $mypercent / 100);
                        }
                    }
                    $item["mytax"] = $mytax;
                    $item["ttax"]  = $totaltax;

                });


            foreach ($listdata as &$v) {
                $weekInfo = getWeekDate(date('Y'), $v['dt']);
                $v['dt']  = $weekInfo[0] . " ~ " . $weekInfo[1];
            }
            unset($v);

        }

        // print_r($listdata);


        $this->assign("agentid", $agentid);
        $this->assign("starttime", $starttime);
        $this->assign("endtime", $endtime);
        $this->assign("proxy", $proxy);
        $this->assign('list', $listdata);
        $this->assign("month", $month);

        return $this->fetch('distribute/incomeSum');
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

        $proxy_id = session("proxy_id");
        $proxy    = session("proxy");


        $where    = array();
        $strwhere = "a.parent_id='" . $proxy_id . "'";
        if (!empty($gamerId)) {
            $where["a.userid"] = $gamerId;
            $strwhere          .= " and a.userid=" . $gamerId;
        }

        if (!empty($gameType)) {
            $where["a.gameid"] = $gameType;
            $strwhere          .= " and a.gameid=" . $gameType;
        }

        $where["a.proxy_id"] = $proxy_id;
        if (!empty($startime)) {
            if (!empty($endTime)) {
                $where['a.addtime'] = array(array('gt', $startime), array('lt', str_replace('-', '/', $endTime)));
                $strwhere           .= " and a.addtime>'" . str_replace('-', '/', $startime) .
                    "' and a.addtime<='" . str_replace('-', '/', $endTime) . "'";
            } else {
                $where['a.addtime'] = array('gt', str_replace('-', '/', $startime));
                $strwhere           .= " and a.addtime>'" . str_replace('-', '/', $startime) . "'";
            }
        }

        $list = Db::name("playerorder")->alias('a')
            ->field("a.proxy_id,a.userid,a.gameid,a.game,a.total_tax,b.percent,a.createtime")
            ->join(" proxy b ", " a.proxy_id=b.code", "LEFT")
            ->where($where)
            ->whereor($strwhere)->order("a.createtime desc")
            ->paginate();

        $this->assign("list", $list);
        $this->assign("gamerid", $gamerId);
        $this->assign("proxy", $proxy);
        $this->assign("starttime", $startime);
        $this->assign("endTime", $endTime);
        $this->assign("gameType", $gameType);
        $this->assign("gametypeList", config('gametype'));
        return $this->fetch('distribute/incomedetail');
    }


    public function playerDetail()
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

//            if(!empty($gameType)){
//                $where["a.gameid"] = $gameType;
//                $strwhere.= " and a.gameid=".$gameType;
//            }

        $where["a.proxy_id"] = $proxy_id;
        if (!empty($startime1)) {
            $startime1 = str_replace("-", "/", $startime1);
            $endTime1  = str_replace("-", "/", $endTime1);
            if (!empty($endTime)) {
                $where['a.regtime'] = array(array('gt', $startime1), array('lt', $endTime1));
                $strwhere           .= " and a.regtime>'" . $startime1 . "' and a.regtime<'" . $endTime1 . "'";
            } else {
                $where['a.regtime'] = array('gt', $startime1);
                $strwhere           .= " and a.regtime>'" . $startime1 . "'";
            }
        }

        $list = Db::name("player")->alias('a')
            ->field(" a.proxy_id,a.userid,a.proxy_name,a.nickname,a.ismobile,a.regtime,a.addtime")
            ->join(" proxy b ", " a.proxy_id=b.code", "LEFT")
            ->where($where)
            ->whereor($strwhere)->order("a.regtime desc")
            ->paginate('15');

        $this->assign("list", $list);
        $this->assign("gamerid", $gamerId);
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

//            if(!empty($gameType)){
//                $where["a.gameid"] = $gameType;
//                $strwhere.= " and a.gameid=".$gameType;
//            }

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
            ->paginate('15');

        $this->assign("list", $list);
        $this->assign("gamerid", $gamerId);
        $this->assign("proxy", $proxy);
        $this->assign("starttime", $startime);
        $this->assign("endTime", $endTime);
        return $this->fetch();
    }
}