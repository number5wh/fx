<?php

namespace app\manage\controller;

use think\Controller;
use think\Session;
use think\Db;
use think\validate;
use Hprose;
use think\log;

class  Company  extends  Controller{

    public function getPlayerList()
    {

        $proxylist = Db::name("proxy")->where("islock","0")->select();		
        foreach($proxylist as $k=>$v){
            $company = new \app\manage\model\Company();
            $ret = $company->getPlayer($v['code']);
            if($ret->data==null){
                //Log::write($ret->message,"DEBUG");
                echo "ID:".$v['code']."has no data!! \r\n";
            } else {
                $retdata = $ret->data;
                if($retdata!=null){

                    foreach($retdata as $key=>$value)
                    {
                        $where=  array(
                            "userid"=>$value->userid,
                            "accountid"=>$value->accountid,
                            "parentid" => $v['code']
                        );

                        $playernum = Db::name("thirdplayer")->where($where)->count();

                        $obj = object_to_array($value);
                        //$obj['proxy_id'] = $v['code'];
                        if($playernum==0){
                            Db::name('thirdplayer')->insert($obj);
                            $data=array(
                                "parent_id"=> $v['parent_id'],
                                "proxy_id"=> $v['code'],
                                "proxy_name"=> $v['nickname'],
                                "userid"=>$value->userid,
                                "accountid"=>$value->accountid,
                                "nickname" => $value->nickname,
                                "ismobile"=>$value->ismobile,
                                "regtime"=>$value->regtime,
                                "addtime"=>Date("Y-m-d H:i:s")
                            );

                            $ret = Db::name("player")->insert($data);
                            if($ret)
                                echo $value->userid." insert  success!!";
                        }
                        // sleep(1000);
                    }
                }
            }
        }
    }


    public function getBillList(){	
        $proxylist = Db::name("proxy")->where("islock","0")->select();			
        $weekInfo = getWeekDate(date('Y'), date('W'));
        foreach($proxylist as $k=>$v){
            $proxy_id = $v["code"];
            $company = new \app\manage\model\Company();
            $ret = $company->getBillList($proxy_id,1,10000);			
            if($ret->data==null){
                //Log::write($ret->message,"DEBUG");
                echo "ID:".$proxy_id."has no data!! \r\n";
            }
            else
            {
                $retdata = $ret->data;               
                if($retdata!=null) {
                    foreach ($retdata as $key => $value) {
                        $sqlwhere11 = array(
                            "time" => $value->time,
                            "userid" => $value->userid,
                            "gameid" => $value->gameid,
                            "game" => $value->game,
                            "tax" => $value->tax
                        );
                        Db::startTrans();
                        try {
                            $playernum = Db::name("third_player_order")->where($sqlwhere11)->count();

                            if ($playernum == 0) {
                                $obj = object_to_array($value);
                                Db::name("third_player_order")->insert($obj);

                                $data = array(
                                    "parent_id" => $v['parent_id'],
                                    "proxy_id" => $proxy_id,
                                    "percent" => $v['percent'],
                                    "userid" => $value->userid,
                                    "gameid" => $value->gameid,
                                    "game" => $value->game,
                                    "addtime" => date("Y-m-d H:i:s", time()),
                                    "total_tax" => $value->tax / 1000,
                                    "createtime" => $value->time,
                                    "week" => $weekInfo[2],
                                    'weekstart' => $weekInfo[0],
                                    'weekend' => $weekInfo[1]

                                );
                                $ret = Db::name("playerorder")->insert($data);
                                if ($ret) {
                                    $totaltax = $value->tax / 1000;
                                    //做个处理、、

                                    $parent_percent = Db::name("proxy")->where("code", $v["parent_id"])->value("percent");

                                    //判断上下级比例是否一样
//                                    if ($parent_percent == $v['percent']) {
//                                        //一样的话下级抽出1%分给上级
//                                        $usertax = $totaltax * ($v["percent"] - 1) / 100;
//                                        $parent_tax = $totaltax*1/100;
//                                    } else {
                                        $usertax = $totaltax * $v["percent"] / 100;
                                        $parent_tax = ($parent_percent - $v["percent"]) / 100 * $totaltax;
                                    //}

                                    $proxy_data = array(
                                        "proxy_id" => $v["code"],
                                        "typeid"   => config('incomelog.income'),
                                        "totaltax" => $totaltax,
                                        "changmoney" => $usertax,
                                        "createtime" => time(),
                                        "descript" => $v["code"] . "代理的玩家税收分成，总金额" . $totaltax,
										"fxtype"   =>1,//新增 用于区分
                                        'createday' => date('Ymd')
                                    );
                                    $parentdata = array(
                                        "proxy_id" => $v["parent_id"],
                                        "typeid"   => config('incomelog.income'),
                                        "totaltax" => $totaltax,
                                        "changmoney" => $parent_tax,
                                        "createtime" => time(),
                                        "descript" => $v["code"] . "给上级税收分成，金额" . $parent_tax,
										"fxtype"   =>2,
                                        'createday' => date('Ymd')
                                    );
                                    Db::name("incomelog")->insert($proxy_data);
                                    Db::name("incomelog")->insert($parentdata);

                                    Db::name("proxy")->where("code", $v["code"])->update([
                                        'balance' => Db::raw('balance+' . $usertax),
                                        'historyin' => Db::raw('historyin+' . $usertax)
                                    ]);

                                    Db::name("proxy")->where("code", $v["parent_id"])->update([
                                        'balance' => Db::raw('balance+' . $parent_tax),
                                        'historyin' => Db::raw('historyin+' . $parent_tax)
                                    ]);

                                    echo $value->userid . " insert  success!!\r\n";
                                }
                            }
                            Db::commit();
                        } catch (\Exception $e) {
                            echo $e->getMessage();
                            //echo $e->getTraceAsString();
                            Db::rollback();
                        }
                    }


                }
            }
        }
    }


    //代理比例升级
    public function upgrade()
    {
        if (date('w') != 1) {
//            echo "not monday";
//            exit;
        }
        //开始处理等级
        $rateData = Db::table('proxypercent')->find(1);
        if(config("upgrade")=="true") {
            $proxylist = Db::name("proxy")
                ->where(['islock'=>0, 'percent'=>['egt',$rateData['level1_rate']]])
                ->order('grade', 'asc')
                ->select();
            //获取可升级的最高比例
            $maxLevel = Db::name('proxyupgrade')->max('percent');

            $today = strtotime(date('Y-m-d 00:00:00'));
//            $beginDay = $today - 7*24*60*60;
            $beginDay = $today - 24*60*60;                        //测试 1天时间
            foreach($proxylist as $k=>$v) {
                if ($v['percent'] >= $maxLevel) {
                    //达到最高等级的忽略
                    continue;
                }
                $proxy_id = $v["code"];

				//先获取所有后代代理数据
                $list = Db::name('teamlevel')->where('parent_id', $proxy_id)->field('proxy_id')->select();
                if ($list) {
                    $list = array_column($list, 'proxy_id');
                }
                //加上自己
                $list[] = $proxy_id;
				//获取所有后代代理的数据+自己的数据
                $where['createtime'] = [['egt', $beginDay], ['lt', $today]];
                $where['proxy_id'] = ['in', $list];
                $where['fxtype'] = 1;//本人的业绩，非给上级分成
                $where['typeid'] = config('incomelog.income');
                $todayMoney = Db::name("incomelog")->where($where)->sum("totaltax");
                //echo "{$v['code']}  money: $todayMoney\r\n";


                $levelup = 0;

                $where1['profit'] = array('elt', $todayMoney);
                $arrUpgrade = Db::name("proxyupgrade")->where($where1)->order("profit desc")->find();
                save_log('apidata/updategrade', $proxy_id."--money-- ".$todayMoney."--grade--".$v["grade"]);
                if (!empty($arrUpgrade)) {
                    $levelup = $arrUpgrade["percent"];
                }
				

                if ($levelup == 0) { //未达到条件
                    continue;
                }

                //判断其上级代理的比率
                $parentInfo = Db::name("proxy")
                    ->where(['code'=>$v['parent_id']])
                    ->find();

                if ($levelup >= $parentInfo['percent']) {
                    $levelup = $parentInfo['percent']-1;
                }
				
				save_log('apidata/updategrade', $proxy_id."--level--".$levelup."--parentpercent--".$parentInfo['percent']."--current--".$v['percent']);

                //获取代理信息
                if ($levelup <= $v['percent']) {
                    //可升级的比例小于等于当前的比例
                    continue;
                }
                Db::name("proxy")->where("code", $v["code"])->update(['percent' => $levelup]);
                $updateData = array(
                    "proxy_id" => $v["code"],
                    "oldpercent" => $v["percent"],
                    "newpercent" => $levelup,
                    "todaymoney" => $todayMoney,
                    "createtime" => time()
                );
                Db::name("proxyupgradelog")->insert($updateData);
                echo $v['code'] . " upgrade  success!!\r\n";
            }
        }
    }


    public function getPayTime(){
        $proxylist = Db::name("player")->select();
        foreach($proxylist as $k=>$v){
            $user_id = $v["userid"];
            $company = new \app\manage\model\Company();
            $ret =  $ret = $company->getPayTime($user_id);
            //print_r($ret);
            if($ret->data==null){
                //Log::write($ret->message,"DEBUG");
                echo "ID:".$user_id."has no data!! \r\n";
            }
            else
            {
                $retdata = $ret->data;
                if($retdata!=null){
                    foreach($retdata as $key=>$value)
                    {
                        $where=  array(
                            "loginid"=>$value->loginid,
                            "updatetime"=>$value->updatetime,
                            "totalfee"=>$value->totalfee
                        );
                        $playernum = Db::name("thirdpaytime")->where($where)->count();
                        if($playernum==0){
                            $obj = object_to_array($value);
                            Db::name("thirdpaytime")->insert($obj);
                            $data=array(
                                "proxy_id"=>  $v["proxy_id"],
                                "userid"=>$value->LoginID,
                                "totalfee"=>$value->TotalFee,
                                "addtime"=> $value->UpdateTime,
                                "createtime"=> time()
                            );

                            $ret = Db::name("paytime")->insert($data);
                            if($ret)
                                echo $value["userid"]." insert  success!!\r\n";
                        }
                    }
                }
                else{
                    echo "has no data! \r\n";
                }
            }
        }

    }



}