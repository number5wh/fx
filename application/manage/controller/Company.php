<?php

namespace app\manage\controller;

use app\manage\model\WithdrawBank;
use redis\Redis;
use think\Controller;
use think\Session;
use think\Db;
use think\validate;
use Hprose;
use think\log;

class  Company extends Controller
{

    public function _initialize()
    {

    }

    //player表  如果proxy_id变化，做新增操作
    public function getPlayerList()
    {
        if (!is_cli()) {
            echo "not cli";
            exit;
        }
        set_time_limit(0);

        $company = new \app\manage\model\Company();
//            if ($v["code"] == "WZ0001620" || $v["code"] == "WZ0001628") {//屏蔽
//                continue;
//            }

        $ret = $company->getPlayer();
        if ($ret->data == null) {
            //Log::write($ret->message,"DEBUG");
            echo "has no data!! \r\n";
        } else {
            $retdata = $ret->data;
            if ($retdata != null) {

                foreach ($retdata as $key => $value) {
                    $proxyInfo = Db::name('proxy')->where(['code' => $value->parentid])->find();
                    if (!$proxyInfo) {
                        $company->updatefxplayerstatus($value->id, $value->classid);
                        continue;
                    }

                    $where = array(
                        "returnid"  => $value->id,
                        "userid"    => $value->userid,
                        "accountid" => $value->accountid,
                        "parentid"  => $value->parentid,
                        "nickname"  => $value->nickname,
                        "regtime"   => $value->regtime,
                        "ismobile"  => $value->ismobile,
                        "classid"   => $value->classid,

                    );

                    $playernum = Db::name("thirdplayer")->where($where)->count();

                    $obj = $where;
                    if ($playernum > 0) {
                        $company->updatefxplayerstatus($value->id, $value->classid);
                        continue;
                    }

                    //$obj["proxy_id"] = $v["code"];
                    Db::name("thirdplayer")->insert($obj);
                    $data = array(
                        "parent_id"  => $proxyInfo["parent_id"],
                        "proxy_id"   => $proxyInfo["code"],
                        "proxy_name" => $proxyInfo["nickname"],
                        "userid"     => $value->userid,
                        "accountid"  => $value->accountid,
                        "nickname"   => $value->nickname,
                        "ismobile"   => $value->ismobile,
                        "regtime"    => $value->regtime,
                        "addtime"    => Date("Y-m-d H:i:s")
                    );
                    $ret  = Db::name("player")->insert($data);

                    echo $value->userid . " insert  success!!";
                    $company->updatefxplayerstatus($value->id, $value->classid);
                    save_log("apidata/getplayer", "insert:" . $value->userid);
                }
//                        else {//此处是更新操作
//                            Db::name("thirdplayer")->where($where)->update($obj);
//                            $data = array(
//                                "parent_id"  => $v["parent_id"],
//                                "proxy_id"   => $v["code"],
//                                "proxy_name" => $v["nickname"],
//                                "userid"     => $value->userid,
//                                "accountid"  => $value->accountid,
//                                "nickname"   => $value->nickname,
//                                "ismobile"   => $value->ismobile,
//                                "regtime"    => $value->regtime,
//                                "addtime"    => Date("Y-m-d H:i:s")
//                            );
//                            $ret = Db::name("player")->where($where)->update($data);
//                        }
                // sleep(1000);
            }
        }
    }


    //推广账单
    public function spreadBillList()
    {
        set_time_limit(0);
        //验证
        $roleId  = input("roleid");
        $amount  = input("amount");
        $orderid = input("orderid");
        $time    = input("time");
        $token   = input("token");

        if (md5(config("spreadkey") . $roleId . $amount . $orderid . $time) !== $token || !$roleId || !$amount || !$time || !$orderid) {
            save_log("apidata/spreadbill", "code: -2, userid:{$roleId}, amount:{$amount}, token wrong or params wrong");
            echo 0;
            exit;
        }
        $weekInfo = getWeekDate(date("Y"), date("W"));

        $proxy = Db::name("player")->where(["userid" => $roleId])->order("id", "desc")->find();
        if (!$proxy || !$proxy['proxy_id']) {
            save_log("apidata/spreadbill", "code: 0, userid:{$roleId}, amount:{$amount}, no proxy");
            echo 2;
        } else {
            $taxArr = getTax($proxy["proxy_id"], $amount, config("spreadrate"));

            if (!$taxArr) {
                save_log("apidata/spreadbill", "code: 1, userid:{$roleId}, amount:{$amount}, proxyid:{$proxy["proxy_id"]} no tax");
                echo 0;
            } else {

                //存入
                if (!Db::name("thirdspreadorder")->where(["userid" => $roleId, "amount" => $amount, "orderid" => $orderid])->count()) {
                    try {
                        Db::startTrans();
                        $res = Db::name("thirdspreadorder")->insertGetId([
                            "userid"     => $roleId,
                            "amount"     => $amount,
                            "orderid"    => $orderid,
                            "time"       => $time,
                            "token"      => $token,
                            "createtime" => time(),
                            "createdate" => date("Y-m-d H:i:s")
                        ]);

                        foreach ($taxArr as $tax) {
                            Db::name("incomelog")->insert([
                                "userid"     => $roleId,
                                "proxy_id"   => $tax["proxy_id"],
                                "typeid"     => config("incomelog.spread"),
                                "totaltax"   => $amount,
                                "changmoney" => $tax["tax"],
                                "createtime" => time(),
                                "descript"   => $tax["proxy_id"] . "代理的玩家:" . $roleId . "推广分成，总金额" . $tax["tax"],
                                "fxtype"     => $tax['level'] == 0 ? 1 : 2,//新增1=直属玩家推广 用于区分
                                "createday"  => date("Ymd"),
                                "addtime"    => date("Y-m-d H:i:s"),
                                "week"       => $weekInfo[2],
                                "weekstart"  => $weekInfo[0],
                                "weekend"    => $weekInfo[1]
                            ]);
                            Db::name("proxy")->where("code", $tax["proxy_id"])->data([
                                "balance"   => Db::raw("balance+" . $tax["tax"]),
                                "historyin" => Db::raw("historyin+" . $tax["tax"])
                            ])->update();

                            $money = Db::name("proxy")->where("code", $tax["proxy_id"])->field('balance,historyin')->find();
                            //金额log
                            Db::name('moneylog')->insert([
                                'type'       => 0,
                                'detail'     => 3,
                                'tax'        => $amount,
                                'money'      => $tax['tax'],
                                'leftmoney'  => $money['balance'],
                                'historyin'  => $money['historyin'],
                                'proxy_id'   => $tax["proxy_id"],
                                'createtime' => date("Y-m-d H:i:s"),
                                'createday'  => date("Ymd")
                            ]);
                            save_log("apidata/spreadbill", "code: 200, userid:{$roleId}, amount:{$amount}, tax:{$tax["tax"]}, percent:{$tax["percent"]}, getrate:{$tax["getrate"]}, proxyid:{$tax["proxy_id"]}, success");
                        }
                        Db::commit();
                        echo 1;
                    } catch (\Exception $e) {
                        Db::rollback();
                        save_log("apidata/spreadbill", "code: -1, userid:{$roleId}, orderid:{$orderid}, amount:{$amount}, msg:{$e->getMessage()}");
                        echo 0;
                    }
                }
            }
        }
    }

    //直属玩家账单
    public function getBillList()
    {
        if (!is_cli()) {
            echo "not cli";
            exit;
        }
        set_time_limit(0);
        $weekInfo = getWeekDate(date("Y"), date("W"));
        save_log("apidata/getBillList", "start");

        $company = new \app\manage\model\Company();
        $ret     = $company->getBillList();
        if ($ret->data == null) {
            //Log::write($ret->message,"DEBUG");
            echo "no data!! \r\n";
        } else {
            $retdata = $ret->data;

            if ($retdata != null && $retdata) {
                foreach ($retdata as $key => $value) {
                    $insertThird = $insertOrder = $insertIncome = [];
                    $allTax      = 0;
                    $proxy_id    = $value->proxyid;
                    $proxyInfo   = Db::name("proxy")->where("code", $proxy_id)->find();
                    if (!$proxyInfo) {
                        continue;
                    }

                    //获取代理级别信息

                    $key = "leveldata_".$proxy_id;
                    $levelData = Redis::getInstance()->get($key);

                    if (!$levelData) {
                        $key2 = "teamlevel_parent_".$proxy_id;
                        $teamlevels = Redis::getInstance()->get($key2);
                        if (!$teamlevels) {
                            $teamlevels = Db::name("teamlevel")->where("proxy_id", $proxy_id)->select();
                            Redis::getInstance()->set($key2, $teamlevels, 0);
                        }


                        $levelData  = [];
                        //自身
                        $levelData[0] = [
                            "proxy_id"  => $proxy_id,
                            "parent_id" => $proxy_id,
                            "level"     => 0,
                            "percent"   => $proxyInfo["percent"]
                        ];
                        if ($teamlevels) {
                            foreach ($teamlevels as $l) {
                                $percent                = Db::name("proxy")->field("percent")->where("code", $l["parent_id"])->find();
                                $levelData[$l["level"]] = [
                                    "proxy_id"  => $proxy_id,
                                    "parent_id" => $l["parent_id"],
                                    "level"     => $l["level"],
                                    "percent"   => $percent["percent"]
                                ];
                            }
                        }
                        Redis::getInstance()->set($key, $levelData, 0);
                    }

                    $sqlwhere11 = array(
                        "returnid" => $value->id,
                        "time"     => $value->time,
                        "userid"   => $value->userid,
                        "gameid"   => $value->gameid,
                        "game"     => $value->game,
                        "tax"      => $value->tax,
                        'classid'  => $value->classid
                    );

                    $playernum = Db::name("third_player_order")->where($sqlwhere11)->count();
                    if ($playernum > 0) {
                        //已处理
                        $company->updateProxyBillStatus($value->id, $value->classid);
                        continue;
                    }

                    $allTax        += $value->tax;
                    $obj           = $sqlwhere11;
                    $insertThird[] = $obj;
                    //Db::name("third_player_order")->insert($obj);
                    $data          = array(
                        "parent_id"  => $proxyInfo["parent_id"],
                        "proxy_id"   => $proxy_id,
                        "percent"    => $proxyInfo["percent"],
                        "userid"     => $value->userid,
                        "gameid"     => $value->gameid,
                        "game"       => $value->game,
                        "addtime"    => date("Y-m-d H:i:s", time()),
                        "total_tax"  => $value->tax / 1000,
                        "createtime" => $value->time,
                        "week"       => $weekInfo[2],
                        "weekstart"  => $weekInfo[0],
                        "weekend"    => $weekInfo[1]
                    );
                    $insertOrder[] = $data;

                    $totaltax = $value->tax / 1000;
                    foreach ($levelData as $level => $lv) {
                        if ($lv["level"] == 0) { //当前运营商
                            $insertIncome[] = [
                                "from_id"    => $proxyInfo["code"],
                                "proxy_id"   => $lv["proxy_id"],
                                "typeid"     => config("incomelog.income"),
                                "totaltax"   => $totaltax,
                                "changmoney" => $totaltax * $lv["percent"] / 100,
                                "createtime" => time(),
                                "descript"   => $proxyInfo["code"] . "代理的玩家税收分成，总金额" . $totaltax * $lv["percent"] / 100,
                                "fxtype"     => 1,//新增 用于区分
                                "createday"  => date("Ymd"),
                                "addtime"    => date("Y-m-d H:i:s"),
                                "week"       => $weekInfo[2],
                                "weekstart"  => $weekInfo[0],
                                "weekend"    => $weekInfo[1]
                            ];
                        } else { //父级代理
                            $getPercent = intval($lv["percent"] - $levelData[$level - 1]["percent"]);
                            if ($getPercent > 0) {
                                $insertIncome[] = [
                                    "from_id"    => $proxyInfo["code"],
                                    "proxy_id"   => $lv["parent_id"],
                                    "typeid"     => config("incomelog.income"),
                                    "totaltax"   => $totaltax,
                                    "changmoney" => $totaltax * $getPercent / 100,
                                    "createtime" => time(),
                                    "descript"   => $proxyInfo["code"] . "给上级税收分成，金额" . $totaltax * $getPercent / 100,
                                    "fxtype"     => 2,//新增 用于区分
                                    "createday"  => date("Ymd"),
                                    "addtime"    => date("Y-m-d H:i:s"),
                                    "week"       => $weekInfo[2],
                                    "weekstart"  => $weekInfo[0],
                                    "weekend"    => $weekInfo[1]
                                ];
                            }
                        }
                    }

                    Db::startTrans();
                    try {
                        if ($insertThird) {
                            Db::name("third_player_order")->insertAll($insertThird);
                        }
                        if ($insertOrder) {
                            Db::name("playerorder")->insertAll($insertOrder);
                        }
                        if ($insertIncome) {
                            Db::name("incomelog")->insertAll($insertIncome);
                        }

                        //计算新增金额
                        foreach ($levelData as $k1 => $v1) {
                            if ($k1 == 0) { //当前代理
                                Db::name("proxy")->where("code", $v1["proxy_id"])->data([
                                    "balance"   => Db::raw("balance+" . $allTax * $v1["percent"] / 100 / 1000),
                                    "historyin" => Db::raw("historyin+" . $allTax * $v1["percent"] / 100 / 1000)
                                ])->update();
                                $money = Db::name("proxy")->where("code", $v1["proxy_id"])->field('balance,historyin')->find();
                                //金额log
                                Db::name('moneylog')->insert([
                                    'type'       => 0,
                                    'detail'     => 0,
                                    'tax'        => $allTax / 1000,
                                    'money'      => $allTax * $v1["percent"] / 100 / 1000,
                                    'leftmoney'  => $money['balance'],
                                    'historyin'  => $money['historyin'],
                                    'proxy_id'   => $v1["proxy_id"],
                                    'createtime' => date("Y-m-d H:i:s"),
                                    'createday'  => date("Ymd")
                                ]);

                                save_log("apidata/getBillList", "proxyId:{$v1["proxy_id"]},addmoney:" . $allTax * $v1["percent"] / 100 / 1000 . ".");
                            } else { //父级代理
                                $getPercent = intval($v1["percent"] - $levelData[$k1 - 1]["percent"]);
                                Db::name("proxy")->where("code", $v1["parent_id"])->data([
                                    "balance"   => Db::raw("balance+" . $allTax * $getPercent / 100 / 1000),
                                    "historyin" => Db::raw("historyin+" . $allTax * $getPercent / 100 / 1000)
                                ])->update();
                                $money = Db::name("proxy")->where("code", $v1["parent_id"])->field('balance,historyin')->find();
                                //金额log
                                Db::name('moneylog')->insert([
                                    'type'       => 0,
                                    'detail'     => 0,
                                    'tax'        => $allTax / 1000,
                                    'money'      => $allTax * $getPercent / 100 / 1000,
                                    'leftmoney'  => $money['balance'],
                                    'historyin'  => $money['historyin'],
                                    'proxy_id'   => $v1["parent_id"],
                                    'createtime' => date("Y-m-d H:i:s"),
                                    'createday'  => date("Ymd")
                                ]);

                                save_log("apidata/getBillList", "proxyId:{$v1["parent_id"]},addmoney:" . $allTax * $getPercent / 100 / 1000 . ".");
                            }
                        }
                        Db::commit();
                        $company->updateProxyBillStatus($value->id, $value->classid);
                        echo $proxy_id . " insert  success!!\r\n";
                    } catch (\Exception $e) {
                        Db::rollback();
                        save_log("apidata/getBillList", "code:fail;proxyId:{$proxy_id},handlemsg:{$e->getMessage()}");
                        echo $proxy_id . " insert fail " . $e->getMessage();
                    }


                }
            }
        }
    }


    //代理比例升级
    public function upgrade()
    {
        exit;
        if (!is_cli()) {
            echo "not cli";
            exit;
        }
        set_time_limit(0);
        $open = Db::table("openupgrade")->find(1);
        if ($open["open"] != 1) {
            echo "not open";
            exit;
        }
        if (date("w") != 1) {
//            echo "not monday";
//            exit;
        }
        //开始处理等级
        $rateData = Db::table("proxypercent")->find(1);
        if (config("upgrade") == "true") {
            $proxylist = Db::name("proxy")
                ->where(["islock" => 0, "percent" => ["egt", $rateData["level1_rate"]]])
                ->order("grade", "asc")
                ->select();
            //获取可升级的最高比例
            $maxLevel = Db::name("proxyupgrade")->max("percent");

            $today    = strtotime(date("Y-m-d 00:00:00"));
            $beginDay = $today - 7 * 24 * 60 * 60;  //7天
            //$beginDay = $today - 24 * 60 * 60;                        //测试 1天时间
            foreach ($proxylist as $k => $v) {
                if ($v["percent"] >= $maxLevel) {
                    //达到最高等级的忽略
                    continue;
                }
                $proxy_id = $v["code"];


                $where["createtime"] = [["egt", $beginDay], ["lt", $today]];
                $where["proxy_id"]   = $proxy_id;
                $where["typeid"]     = ["in", [0, 4]];
                $todayMoney          = Db::name("incomelog")->where($where)->sum("totaltax");
                //echo "{$v["code"]}  money: $todayMoney\r\n";

                $levelup = 0;

                $where1["profit"] = array("elt", $todayMoney);
                $arrUpgrade       = Db::name("proxyupgrade")->where($where1)->order("profit desc")->find();
                save_log("apidata/updategrade", $proxy_id . "--money-- " . $todayMoney . "--grade--" . $v["grade"]);
                if (!empty($arrUpgrade)) {
                    $levelup = $arrUpgrade["percent"];
                }

                if ($levelup == 0) { //未达到条件
                    continue;
                }

                //判断其上级代理的比率
                $parentInfo = Db::name("proxy")
                    ->where(["code" => $v["parent_id"]])
                    ->find();

                if ($levelup >= $parentInfo["percent"]) {
                    $levelup = $parentInfo["percent"] - 1;
                }

                save_log("apidata/updategrade", $proxy_id . "--level--" . $levelup . "--parentpercent--" . $parentInfo["percent"] . "--current--" . $v["percent"]);

                //获取代理信息
                if ($levelup <= $v["percent"]) {
                    //可升级的比例小于等于当前的比例
                    continue;
                }
                Db::name("proxy")->where("code", $v["code"])->update(["percent" => $levelup]);
                $updateData = array(
                    "proxy_id"   => $v["code"],
                    "oldpercent" => $v["percent"],
                    "newpercent" => $levelup,
                    "todaymoney" => $todayMoney,
                    "createtime" => time()
                );
                Db::name("proxyupgradelog")->insert($updateData);
                echo $v["code"] . " upgrade  success!!\r\n";
            }
        }
    }


    public function getPayTime()
    {
        if (!is_cli()) {
            echo "not cli";
            exit;
        }
        set_time_limit(0);

        $company = new \app\manage\model\Company();
        $ret     = $company->getPayTime();
        //print_r($ret);
        if ($ret->data == null) {
            //Log::write($ret->message,"DEBUG");
            echo "no data!! \r\n";
        } else {
            $retdata = $ret->data;
            if ($retdata != null) {
                foreach ($retdata as $key => $value) {
                    $where     = array(
                        "typeid"     => $value->typeid,
                        "loginid"    => $value->loginid,
                        "updatetime" => $value->updatetime,
                        "totalfee"   => $value->totalfee
                    );
                    $playernum = Db::name("thirdpaytime")->where($where)->count();
                    if ($playernum == 0) {
                        $obj = object_to_array($value);
                        Db::name("thirdpaytime")->insert($obj);
                        $proxyId = Db::name("player")->where(['userid' => $value->loginid])->field('proxy_id')->order("id", "desc")->find();
                        $data    = array(
                            "typeid"     => $value->typeid,
                            "proxy_id"   => $proxyId ? $proxyId['proxy_id'] : '',
                            "userid"     => $value->loginid,
                            "totalfee"   => $value->totalfee,
                            "addtime"    => $value->updatetime,
                            "createtime" => time()
                        );

                        $ret = Db::name("paytime")->insert($data);
                        if ($ret)
                            echo $data["userid"] . " insert  success!!\r\n";
                    }
                }
            } else {
                echo "has no data! \r\n";
            }
        }
    }

    //获取所有玩家数据
    public function getAllPlayerList()
    {
        if (!is_cli()) {
            echo "not cli";
            exit;
        }
        set_time_limit(0);
        $company = new \app\manage\model\Company();
        $ret     = $company->getAllPlayerList();

        if ($ret->data == null) {
            //Log::write($ret->message,"DEBUG");
            echo " no data!! \r\n";
        } else {
            $retdata = $ret->data;
            if ($retdata != null) {

                foreach ($retdata as $key => $value) {
                    $where     = array(
                        "userid"    => $value->userid,
                        "accountid" => $value->accountid,
                    );
                    $playernum = Db::name("thirdplayerall")->where($where)->count();

                    if ($playernum == 0) {
                        //插入
                        Db::name("thirdplayerall")->insert([
                            "returnid"  => $value->id,
                            "parentid"  => $value->parentid,
                            "userid"    => $value->userid,
                            "accountid" => $value->accountid,
                            "nickname"  => $value->nickname,
                            "ismobile"  => $value->ismobile,
                            "regtime"   => $value->regtime,
                        ]);
                        $data = array(
                            "parent_id"  => "",
                            "proxy_id"   => $value->parentid,
                            "proxy_name" => "",
                            "userid"     => $value->userid,
                            "accountid"  => $value->accountid,
                            "nickname"   => $value->nickname,
                            "ismobile"   => $value->ismobile,
                            "regtime"    => $value->regtime,
                            "addtime"    => date("Y-m-d H:i:s")
                        );

                        $ret = Db::name("playerall")->insert($data);
                        save_log("apidata/getallplayer", "insert:" . $value->userid);
                    } else {
                        //更新
                        Db::name("thirdplayerall")->where($where)->update([
                            "returnid"  => $value->id,
                            "parentid"  => $value->parentid,
                            "userid"    => $value->userid,
                            "accountid" => $value->accountid,
                            "nickname"  => $value->nickname,
                            "ismobile"  => $value->ismobile,
                            "regtime"   => $value->regtime,
                        ]);
                        $data = array(
                            "parent_id"  => "",
                            "proxy_id"   => $value->parentid,
                            "proxy_name" => "",
                            "userid"     => $value->userid,
                            "accountid"  => $value->accountid,
                            "nickname"   => $value->nickname,
                            "ismobile"   => $value->ismobile,
                            "regtime"    => $value->regtime,
                            "addtime"    => date("Y-m-d H:i:s")
                        );

                        $ret = Db::name("playerall")->where($where)->update($data);
                        save_log("apidata/getallplayer", "update:" . $value->userid);
                    }

                    $company->updatefxalluserstatus($value->id);
                    // sleep(1000);
                }
            }
        }
    }


    //按日汇总数据
    public function spreadsum()
    {
        if (!is_cli()) {
            echo "not cli";
            exit;
        }
        set_time_limit(0);
        if (date('G') == 0 && intval(date('i')) <=15) {
            $date = date('Y-m-d', strtotime('-1day'));
//        $date = "2019-07-09";
            $day = date('Ymd', strtotime('-1day'));
//        $day = "20190709";
        } else {
            $date = date("Y-m-d");
//        $date = "2019-07-09";
            $day = date("Ymd");
//        $day = "20190709";
        }

        $month     = date("Ym");
        $proxylist = Db::name("proxy")->where("islock", 0)->select();
        $weekInfo  = getWeekDate(date("Y"), date("W"));
        foreach ($proxylist as $proxy) {
            //今日注册人数
            $regnum = Db::name("player")
                ->where([
                    "addtime"  => ["like", $date . "%"],
                    "proxy_id" => $proxy["code"]
                ])->count();
            //绑定手机人数
            $bindnum = Db::name("player")
                ->where([
                    "addtime"  => ["like", $date . "%"],
                    "proxy_id" => $proxy["code"],
                    "ismobile" => "true"
                ])->count();
            //提现次数
            $checknum = Db::name("checklog")
                ->where([
                    "addtime"  => ["like", $date . "%"],
                    "proxy_id" => $proxy["code"],
                    "status"   => 5
                ])->count();

            //提现金额
            $summoney = Db::name("checklog")
                ->where([
                    "addtime"  => ["like", $date . "%"],
                    "proxy_id" => $proxy["code"],
                    "status"   => 5
                ])->sum('amount');

            //直营总税收&&直营总收入
            $selfdata = Db::name("incomelog")
                ->where([
                    "proxy_id" => $proxy["code"],
                    "typeid"   => ["in", [0, 4]],
                    "fxtype"   => 1,
                    "addtime"  => ["like", $date . "%"]
                ])->field('sum(totaltax) totaltax, sum(changmoney) changmoney')->find();

            //下级代理团队总税收 && 下级代理团队总收入
            $teamdata = Db::name("incomelog")
                ->where([
                    "proxy_id" => $proxy["code"],
                    "typeid"   => ["in", [0, 4]],
                    "fxtype"   => 2,
                    "addtime"  => ["like", $date . "%"]
                ])->field('sum(totaltax) totaltax, sum(changmoney) changmoney')->find();

            //总税收&&总收入
            $totaldata = Db::name("incomelog")
                ->where([
                    "proxy_id" => $proxy["code"],
                    "typeid"   => ["in", [0, 4]],
                    "addtime"  => ["like", $date . "%"]
                ])->field('sum(totaltax) totaltax, sum(changmoney) changmoney')->find();

            //充值总次数
            $paynum = Db::name("paytime")
                ->where([
                    "proxy_id" => $proxy["code"],
                    "addtime"  => ["like", $date . "%"]
                ])
                ->count();
            if ($summoney || $paynum || $selfdata['totaltax'] || $selfdata['changmoney'] || $checknum || $bindnum || $regnum
                || $totaldata['totaltax'] || $totaldata['changmoney'] || $teamdata['totaltax'] || $teamdata['changmoney']
            ) {
                $where = [
                    "day"      => $day,
                    "proxy_id" => $proxy["code"]
                ];
                $data  = [
                    "day"             => $day,
                    "month"           => $month,
                    "proxy_id"        => $proxy["code"],
                    //历史总收入 总税收
                    'totalin'         => $totaldata['changmoney'],
                    'historytax'      => $totaldata['totaltax'],
                    //直营
                    "totaltax"        => $selfdata['totaltax'],
                    "totalchangmoney" => $selfdata['changmoney'],
                    //下级代理团队
                    "teamtax"         => $teamdata['totaltax'],
                    "teamchangmoney"  => $teamdata['changmoney'],

                    "regnum"     => $regnum,
                    "bindnum"    => $bindnum,
                    "paynum"     => $paynum,
                    "checknum"   => $checknum,
                    'checkmoney' => $summoney,


                    "updatetime" => date("Y-m-d H:i:s"),
                    "week"       => $weekInfo[2],
                    "weekstart"  => $weekInfo[0],
                    "weekend"    => $weekInfo[1]
                ];
                $find  = Db::name("spreadsum")->where($where)->find();

                if (!$find) {
                    Db::name("spreadsum")->insert($data);
                } else {
                    Db::name("spreadsum")->where(["id" => $find["id"]])->update($data);
                }
            }
        }
    }

    //总的汇总数据
    public function sumdata()
    {

        $proxylist = Db::name("spreadsum")->distinct(true)->field('proxy_id')->select();
        $proxylist = array_column($proxylist, 'proxy_id');
        if ($proxylist) {
            foreach ($proxylist as $proxy) {
                $sumdata = Db::name('spreadsum')->field('sum(regnum) regnum, sum(bindnum) bindnum, sum(paynum) paynum, sum(checknum) checknum,
                sum(checkmoney) checkmoney, sum(totalin) totalin, sum(historytax) historytax, sum(totaltax) totaltax,
                  sum(totalchangmoney) totalchangmoney, sum(teamtax) teamtax, sum(teamchangmoney) teamchangmoney')->where(['proxy_id' => $proxy])->find();
                $data    = [
                    "proxy_id"        => $proxy,
                    //历史总收入 总税收
                    'totalin'         => $sumdata['totalin'],
                    'historytax'      => $sumdata['historytax'],
                    //直营
                    "totaltax"        => $sumdata['totaltax'],
                    "totalchangmoney" => $sumdata['totalchangmoney'],
                    //下级代理团队
                    "teamtax"         => $sumdata['teamtax'],
                    "teamchangmoney"  => $sumdata['teamchangmoney'],

                    "regnum"     => $sumdata['regnum'],
                    "bindnum"    => $sumdata['bindnum'],
                    "paynum"     => $sumdata['paynum'],
                    "checknum"   => $sumdata['checknum'],
                    'checkmoney' => $sumdata['checkmoney'],

                    "updatetime" => date("Y-m-d H:i:s"),
                ];
                $find    = Db::name("sumdata")->where(['proxy_id' => $proxy])->find();

                if (!$find) {
                    Db::name("sumdata")->insert($data);
                } else {
                    Db::name("sumdata")->where(["id" => $find["id"]])->update($data);
                }

            }
        }
    }


    //重建teamlevel表数据
    public function remakelevel()
    {
        if (!is_cli()) {
            echo "not cli";
            exit;
        }
        set_time_limit(0);
        Db::execute("truncate table teamlevel");

        $index = 2;
        Db::startTrans();
        try {
            while (true) {
                $list = Db::name("proxy")->where(["grade" => $index])->select();
                if (!$list) {
                    echo "success";
                    Db::commit();
                    exit;
                }
                foreach ($list as $v) {
                    $data   = [];
                    $data[] = [
                        "proxy_id"  => $v["code"],
                        "parent_id" => $v["parent_id"],
                        "level"     => 1
                    ];
                    //查询上级level
                    $levelList = Db::name("teamlevel")->where(["proxy_id" => $v["parent_id"]])->select();
                    if ($levelList) {
                        foreach ($levelList as $level) {
                            $data[] = [
                                "proxy_id"  => $v["code"],
                                "parent_id" => $level["parent_id"],
                                "level"     => $level["level"] + 1
                            ];
                        }
                        unset($levelList);
                    }
                    Db::name("teamlevel")->insertAll($data);
                }
                $index++;
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
            Db::rollback();
            exit;
        }

    }


    //处理银行卡提现
    public function withdrawbank()
    {
        //status=1未处理 2=审核通过 3=拒绝 4=已完成 5=银行处理中
//        if (!is_cli()) {
//            echo "not cli";
//            exit;
//        }
        set_time_limit(0);
//        $account = "6221884010007457076";
//        $amount=10;
//        $name="谢秀机";
//        $bank = "PSBC";
//        $orderId = date("YmdHis").rand(1000,9999);
//        $model = new WithdrawBank();
//        $city = "福州";
//        $province="福建";
//       // $res = $model->addOrder($account, $amount, $name, $orderId);
//        $res = $model->addCard($account, $name, $bank, $city, $province); //新增银行卡
//        //转账处理
//        $res2 = $model->addOrder($account, $amount, $name, $orderId);
//        var_dump($res,0,$res2);
//        die;

        save_log("apidata/withdrawbank", "start");
        //查询审核通过的  银行的
        $list = Db::name("checklog")->where(["status" => 2, "checktype" => 2])->select();
        if (!$list) {
            save_log("apidata/withdrawbank", "end no data");
            exit;
        }

        foreach ($list as $k => $v) {
            try {
                Db::startTrans();
                if (!$v["orderid"] || !$v["bank"] || !$v["name"] || !$v["cardaccount"] || $v["amount"] <= 0 || !$v["city"] || !$v["province"]) { //参数判断
                    save_log("apidata/withdrawbank", "proxyid: " . $v["proxy_id"] . " orderid: " . $v["orderid"] . " has wrong data");
                    Db::name("checklog")->where(["id" => $v["id"]])->update(["status" => 3, "descript" => "提现金额或提现账号有误或省市信息未填"]);
                    //金额回退
//
//                    if (config("isTax")) {
//                        $backMoney = $v["amount"]+$v["tax"];
//                    } else {
//                        $backMoney = $v["amount"];
//                    }
//                    $backMoney = $v["amount"];
                    Db::name("proxy")->where("code", $v["proxy_id"])->update(["balance" => Db::raw("balance+" . $v["amount"])]);
                    $money = Db::name("proxy")->where("code", $v["proxy_id"])->field('balance,historyin')->find();
                    //金额log
                    Db::name('moneylog')->insert([
                        'type'       => 0,
                        'detail'     => 2,
                        'tax'        => 0,
                        'money'      => $v["amount"],
                        'leftmoney'  => $money['balance'],
                        'historyin'  => $money['historyin'],
                        'proxy_id'   => $v["proxy_id"],
                        'createtime' => date("Y-m-d H:i:s"),
                        'createday'  => date("Ymd")
                    ]);
                    $data1 = array(
                        "typeid"     => 2,
                        "proxy_id"   => $v["proxy_id"],
                        "changmoney" => $v["amount"],
                        "descript"   => "审核拒绝退还账户",
                        "createtime" => time(),
                        "createday"  => date("Ymd")
                    );
                    Db::name("incomelog")->insert($data1);
                } else {
                    $model = new WithdrawBank();
                    Db::name("checklog")->where(["id" => $v["id"]])->update(["status" => 5, "descript" => "银行处理中"]);
                    $res1 = $model->addCard($v["cardaccount"], $v["name"], $v["bank"], $v["city"], $v["province"]); //新增银行卡
                    $res2 = $model->addOrder($v["cardaccount"], $v["amount"], $v["name"], "fz" . $v["orderid"]);
                    $res2 = json_decode($res2, true);
                    if ($res2["success"] == true) {
                        Db::name("checklog")->where(["id" => $v["id"]])->update(["trans_orderid" => $res2["data"]["order_id"], "descript" => "银行处理中"]);
                        save_log("apidata/withdrawbank", "proxyid: " . $v["proxy_id"] . " orderid: " . $v["orderid"] . " success");
                    } else {
                        //失败
                        save_log("apidata/withdrawbank", "proxyid: " . $v["proxy_id"] . " orderid: " . $v["orderid"] . " msg: " . $res2["message"] . " code: " . $res2["error_code"]);
                        //状态更新
                        Db::name("checklog")->where(["id" => $v["id"]])->update(["status" => 3, "descript" => $res2["error_code"] . ":" . $res2["message"]]);
                        //金额回退
                        Db::name("proxy")->where("code", $v["proxy_id"])->update(["balance" => Db::raw("balance+" . $v["amount"])]);
                        $money = Db::name("proxy")->where("code", $v["proxy_id"])->field('balance,historyin')->find();
                        //金额log
                        Db::name('moneylog')->insert([
                            'type'       => 0,
                            'detail'     => 2,
                            'tax'        => 0,
                            'money'      => $v["amount"],
                            'leftmoney'  => $money['balance'],
                            'historyin'  => $money['historyin'],
                            'proxy_id'   => $v["proxy_id"],
                            'createtime' => date("Y-m-d H:i:s"),
                            'createday'  => date("Ymd")
                        ]);
                        //incomelog
                        $data1 = array(
                            "typeid"     => 2,
                            "proxy_id"   => $v["proxy_id"],
                            "changmoney" => $v["amount"],
                            "descript"   => $res2["error_code"] . ":" . $res2["message"],
                            "createtime" => time(),
                            "createday"  => date("Ymd")
                        );
                        Db::name("incomelog")->insert($data1);
                    }
                }

                Db::commit();

            } catch (\Exception $e) {
                Db::rollback();
                save_log("apidata/withdrawbank", "proxyid: " . $v["proxy_id"] . " orderid: fz" . $v["orderid"] . " wrongmsg: " . $e->getMessage());
            }
        }
        save_log("apidata/withdrawbank", "end");
    }

    /**
     * 提现回调
     */
//    public function withdrawnotify()
//    {
//        $get = file_get_contents("php://input");
//        save_log("apidata/withdrawnotify", $get);
//        $header = $_SERVER["HTTP_TLYHMAC"];
//        save_log("apidata/withdrawnotify", "header:" . $header);
//        $receive = json_decode($get, true);
//        $model   = new WithdrawBank();
//        $model->notify($receive, $header, file_get_contents("php://input"));
//    }
    public function withdrawnotify()
    {
        $get = $_GET;
        save_log("apidata/withdrawnotify", json_encode($get));
        $model   = new WithdrawBank();
        $model->notify($_GET);
    }

}