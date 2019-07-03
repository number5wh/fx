<?php

namespace app\admin\controller;

use app\admin\controller\Base;
use app\model\Teamlevel;
use think\captcha\Captcha;
use think\Db;
use think\model;
use think\Paginator;
use think\log;
use app\admin\model\Sms;
use think\Request;


class Proxy extends Base
{
    protected $rateData = [];

    public function __construct()
    {
        parent::__construct();
        $this->rateData = Db::table('proxypercent')->find(1);
    }

    public function index()
    {
        $proxy   = session("proxy");
        $isadmin = 0;
        if ($proxy['grade'] == 1) {//管理员
            $percent = $this->rateData['general_rate'];
            $info    = '可设最大值为：' . $percent;
            $isadmin = 1;

        } else {
            //可开同级
            //$percent = $proxy['percent'] >= $this->rateData['level1_rate'] ? $this->rateData['level1_rate'] : ($proxy['percent']);
            //不可开同级
            //差价2%，并且渠道代理下级最高为80%
            if ($proxy['type'] == 2) {
                //渠道
                $percent = $proxy['percent'] - 2 > $this->rateData['qd_level1rate'] ? $this->rateData['qd_level1rate'] : ($proxy['percent'] - 2);

            } else {
                $percent = $proxy['percent'] - 2 > $this->rateData['level1_rate'] ? $this->rateData['level1_rate'] : ($proxy['percent'] - 2);
            }
            $info = '可设最大值为：' . $percent;
        }

        $this->assign('info', $info);
        $this->assign('percent', $percent);
        $this->assign('isadmin', $isadmin);
        $this->assign('proxytype', $proxy['type']);
        return $this->fetch();
    }

    public function getIndex()
    {
        $status   = input("status");
        $agentid  = input("agentId");
        $phone    = input("phone");
        $regstart = input("startTime");
        $regend   = input("endTime");
        $minmoney = input("minMoney");
        $maxmoney = input("maxMoney");
        $nickname = input("nickName");
        $username = input("account");
        $page     = intval(input('page')) > 0 ? intval(input('page')) : 1;
        $limit    = intval(input('limit')) > 0 ? intval(input('limit')) : 10;

        $proxy    = session("proxy");
        $where = array();
        if ($proxy['code'] == 1) { //管理员查全部

        } else {
            if ($proxy['type'] == 2) {
                //渠道查渠道的所有下级
                $subList = Db::table('teamlevel')->where(['parent_id' => $proxy['code']])->select();
                if ($subList) {
                    $subListId = array_column($subList, 'proxy_id');
                } else {
                    $subListId = [];
                }
                $where['code'] = ['in', $subListId];
            } else {
                //普通代理
                $where["parent_id"] = $proxy["code"];
            }
        }

        $where['type'] = 0;//普通代理
        if (!empty($status)) {
            $where["islock"] = $status;
        } else {
            $status = "";
        }


        if (!empty($agentid)) {
            $where['code'] = $agentid;
        }

        if (!empty($phone)) {
            $where['bind_mobile'] = $phone;
        }

        if (!empty($regstart)) {
            if (!empty($regend)) {
                $where['regtime'] = array(array('gt', $regstart), array('lt', $regend));
            } else {
                $where['regtime'] = array('gt', $regstart);
            }
        }

        if (!empty($minmoney)) {
            if (!empty($maxmoney)) {
                $where['balance'] = array(array('gt', $minmoney), array('lt', $maxmoney));
            } else {
                $where['balance'] = array('gt', $minmoney);
            }
        }

        if (!empty($nickname)) {
            $where["nickname"] = ['like', '%' . $nickname . '%'];;
        }

        if (!empty($username)) {
            $where["username"] = ['like', '%' . $username . '%'];;
        }

        //查询满足要求的总的记录数
        $count = Db::table('proxy')->where($where)->count();

        $field     = "id,code,percent,type,bind_mobile,lock,balance,historyin,username,nickname,regtime,last_login,descript";
        $list      = Db::table('proxy')->field($field)->where($where)->order('id desc ')->page($page, $limit)->select();
        $today     = strtotime(date('Y-m-d 00:00:00'));
        $yesterday = $today - 24 * 3600;
        foreach ($list as &$item) {
            //直营税收
            $selfget = Db::name("incomelog")
                ->where([
                    'proxy_id' => $item['code'],
                    'typeid'   => ['in', [0, 4]],
                    'fxtype'   => 1, //自己的玩家收入
                ])->sum('totaltax');
            //团队税收
            $teamget = Db::name("incomelog")
                ->where([
                    'proxy_id' => $item['code'],
                    'typeid'   => ['in', [0, 4]],
                    'fxtype'   => 2 //下级团队的
                ])->sum('totaltax');
            //历史总收入
            $allget = Db::name("incomelog")
                ->where([
                    'proxy_id' => $item['code'],
                    'typeid'   => ['in', [0, 4]],
                ])->sum('changmoney');
            //昨日总收入
            $yesterdaytt = Db::name("incomelog")
                ->where([
                    'proxy_id'   => $item['code'],
                    'typeid'     => ['in', [0, 4]],
                    'createtime' => [['egt', $yesterday], ['lt', $today]]
                ])
                ->sum('changmoney');
            //总充值
            $recharge          = Db::name('paytime')
                ->where([
                    'proxy_id' => $item['code']
                ])
                ->sum('totalfee');
            $item['yesterday'] = floor($yesterdaytt * 100) / 100;
            $item['selfget']   = floor($selfget * 100) / 100;
            $item['teamget']   = floor($teamget * 100) / 100;
            $item['allget']    = floor($allget * 100) / 100;
            $item['recharge']  = floor($recharge * 100) / 100;

//            if ($item["bind_mobile"] == '')
//                $item["bind_mobile"] = "否";
//            else
//                $item["bind_mobile"] = "是";
//
//            if ($item["lock"] == '1')
//                $item["lock"] = "是";
//            else
//                $item["lock"] = "否";
        }

        unset($item);
        return json(['code' => 0, 'count' => $count, 'data' => $list]);
    }

    //获取子代理
    public function getChildren()
    {
        $agentid = input("code");
        $proxy   = session("proxy");
        $isadmin = $proxy['code'] == 1 ? 1 : 0;
        $info    = Db::name('proxy')->where('code', $agentid)->field('nickname')->find();

        $this->assign('isadmin', $isadmin);
        $this->assign('agentId', $agentid);
        $this->assign('nickname', $info['nickname']);
        $this->assign('proxytype', $proxy['type']);
        return $this->fetch();
    }

    public function getChildrenList()
    {
        $agentid = input("code");
        $page    = intval(input('page')) > 0 ? intval(input('page')) : 1;
        $limit   = intval(input('limit')) > 0 ? intval(input('limit')) : 10;

        $where              = array();
        $where["parent_id"] = $agentid;
        $where['type']      = 0;//普通代理
        //print_r($where);

        //查询满足要求的总的记录数
        $count = Db::table('proxy')->where($where)->count();

        $field     = "id,code,percent,type,bind_mobile,lock,balance,historyin,username,nickname,regtime,last_login,descript";
        $list      = Db::table('proxy')->field($field)->where($where)->order('id desc ')->page($page, $limit)->select();
        $today     = strtotime(date('Y-m-d 00:00:00'));
        $yesterday = $today - 24 * 3600;
        foreach ($list as &$item) {
            //直营税收
            $selfget = Db::name("incomelog")
                ->where([
                    'proxy_id' => $item['code'],
                    'typeid'   => ['in', [0, 4]],
                    'fxtype'   => 1, //自己的玩家收入
                ])->sum('totaltax');
            //团队税收
            $teamget = Db::name("incomelog")
                ->where([
                    'proxy_id' => $item['code'],
                    'typeid'   => ['in', [0, 4]],
                    'fxtype'   => 2 //下级团队的
                ])->sum('totaltax');
            //历史总收入
            $allget = Db::name("incomelog")
                ->where([
                    'proxy_id' => $item['code'],
                    'typeid'   => ['in', [0, 4]],
                ])->sum('changmoney');
            //昨日总收入
            $yesterdaytt = Db::name("incomelog")
                ->where([
                    'proxy_id'   => $item['code'],
                    'typeid'     => ['in', [0, 4]],
                    'createtime' => [['egt', $yesterday], ['lt', $today]]
                ])
                ->sum('changmoney');
            //总充值
            $recharge          = Db::name('paytime')
                ->where([
                    'proxy_id' => $item['code']
                ])
                ->sum('totalfee');
            $item['yesterday'] = floor($yesterdaytt * 100) / 100;
            $item['selfget']   = floor($selfget * 100) / 100;
            $item['teamget']   = floor($teamget * 100) / 100;
            $item['allget']    = floor($allget * 100) / 100;
            $item['recharge']  = floor($recharge * 100) / 100;
        }

        unset($item);
        return json(['code' => 0, 'count' => $count, 'data' => $list]);
    }


    public function checkexist()
    {
        $accountname = input("account");
        $where       = array('username' => $accountname);
        $ret         = Db::name("proxy")->where($where)->count();
        if ($ret > 0) {
            return true;
        } else {
            return false;
        }

    }

    public function editProxyInfo()
    {
        $code  = input("code");
        $proxy = session("proxy");
        if (!$code) {
            return $this->showmsg(0, "参数为空", '', '', null);
        }
        $proxyInfo = Db::name("proxy")->where("code", $code)->find();
        if (!$proxyInfo) {
            return $this->showmsg(0, "找不到该代理信息", '', '', null);
        }


        //判断当前代理是否是编辑人下级(管理员和渠道额外判断)
        if ($proxy['code'] == 1 ) {

        } else {
            if ($proxy['type'] == 2) {
                //获取渠道所有后代
                $subList = Db::table('teamlevel')->where(['parent_id' => $proxy['code']])->select();
                if ($subList) {
                    $subListId = array_column($subList, 'proxy_id');
                }
                if (!in_array($code, $subListId)) {
                    return $this->showmsg(0, "无权限修改此代理信息", '', '', null);
                }
            } else {
                //普通代理
                if ($proxyInfo['parent_id'] != $proxy['code']) {
                    return $this->showmsg(0, "无权限修改此代理信息", '', '', null);
                }
            }
        }


        $canset = 0; //能否设置比例
        $info   = '';
        if ($proxy['code'] == 1) {//管理员
            $parentProxy = Db::name("proxy")->where("code", $proxyInfo['parent_id'])->find();
//            if ($parentProxy['type'] == 2) {
//                //父级是渠道
//                //渠道下级最高比例
//                $qdpercent = $this->rateData['qd_level1rate'];
//                //判断父级的比例
//                $parentPercent = $parentProxy['percent'];
//                $percent = $parentPercent - 2 >= $qdpercent ? $qdpercent : $parentPercent - 2;
//
//            } else {
//                if ($parentProxy['code'] == 1) {
//                    //父级是admin
//                    $percent = $this->rateData['general_rate'];
//                } else {
//
//                    //父级是普通代理
//                    $ptpercent = $this->rateData['level1_rate'];
//                    //判断父级的比例
//                    $parentPercent = $parentProxy['percent'];
//                    $percent = $parentPercent - 2 >= $ptpercent ? $ptpercent : $parentPercent - 2;
//                }
//            }

            if ($parentProxy['code'] == 1) {
                //父级是admin
                $percent = $this->rateData['general_rate'];
            } else {

                if ($parentProxy['type'] == 2) {
                    //渠道
                    $parentPercent = $parentProxy['percent'];
                    $percent       = $parentPercent - 2;
//                    $percent = $percent >= $this->rateData['qd_level1rate'] ? $this->rateData['qd_level1rate'] : $percent;
                } else {
                    $parentPercent = $parentProxy['percent'];
                    $percent       = $parentPercent - 2;
//                    $percent = $percent >= $this->rateData['level1_rate'] ? $this->rateData['level1_rate'] : $percent;
                }

            }

            $info    = '上级比例为'.$parentProxy['percent'].', 可设最大值为：' . $percent;
        } else {
            //渠道可开最大值80%  所有的代理差价2%
            if ($proxy['type'] == 2) {
                //渠道代理
//                if ($proxyInfo['percent'] >= $this->rateData['qd_level1rate']) {//下级代理是否已超出限定值
//                    $info = '';
//                } else {
//                    $percent = $proxy['percent'] -2  > $this->rateData['qd_level1rate'] ? $this->rateData['qd_level1rate'] : $proxy['percent'] - 2;
//                    $info    = '可设最大值为：' . $percent;
//                    $canset  = 1;
//                }
                $parentProxy = Db::name("proxy")->where("code", $proxyInfo['parent_id'])->find();
                $percent = $parentProxy['percent'] - 2;
                if ($parentProxy['type'] == 2) {
                    //渠道直属下级可改到80
//                    $percent = $percent >= $this->rateData['qd_level1rate'] ? $this->rateData['qd_level1rate'] : $percent;
                } else {
                    //其他只能改到60
//                    $percent = $percent >= $this->rateData['level1_rate'] ? $this->rateData['level1_rate'] : $percent;
                }

                $info    = '上级比例为'.$parentProxy['percent'].', 可设最大值为：' . $percent;
                $canset  = 1;
            } else {
                //普通代理
//                if ($proxyInfo['percent'] >= $this->rateData['level1_rate']) {//下级代理是否已超出限定值
//                    $info = '';
//                } else {
//                    $percent = $proxy['percent'] -2  > $this->rateData['level1_rate'] ? $this->rateData['level1_rate'] : $proxy['percent'] - 2;
//                    $info    = '可设最大值为：' . $percent;
//                    $canset  = 1;
//                }
                $percent = $proxy['percent'] - 2 >= $this->rateData['level1_rate'] ? $this->rateData['level1_rate'] : $proxy['percent'] - 2;
                $info    = '上级比例为'.$proxy['percent'].', 可设最大值为：' . $percent;
                $canset  = 1;
            }

        }

        return json(['code' => 1, 'info2' => $info, 'canset' => $canset]);

    }

    public function editProxy()
    {
        $code     = input("proxy_code");
        $passord  = input("proxy_password");
        $nickname = input("proxy_nickname");
        $mobile   = input("mobile");
        $percent  = intval(input("proxy_rate"));
        $remark   = input("proxy_comment");
        $proxy    = session("proxy");
        if (!$code || $nickname == '' || $percent == '') {
            return $this->showmsg(0, "参数为空", '', '', null);
        }
        if ($passord && strlen($passord) < 6) {
            return $this->showmsg(0, "密码长度至少为6位", '', '', null);
        }
        $proxyInfo = Db::name("proxy")->where("code", $code)->find();
        if (!$proxyInfo) {
            return $this->showmsg(0, "找不到该代理信息", '', '', null);
        }
        //判断当前代理是否是编辑人下级(管理员和渠道额外判断)
        if ($proxy['code'] == 1 ) {

        } else {
            if ($proxy['type'] == 2) {
                //获取渠道所有后代
                $subList = Db::table('teamlevel')->where(['parent_id' => $proxy['code']])->select();
                if ($subList) {
                    $subListId = array_column($subList, 'proxy_id');
                }
                if (!in_array($code, $subListId)) {
                    return $this->showmsg(0, "无权限修改此代理信息", '', '', null);
                }
            } else {
                //普通代理
                if ($proxyInfo['parent_id'] != $proxy['code']) {
                    return $this->showmsg(0, "无权限修改此代理信息", '', '', null);
                }
            }
        }

        //昵称
        $data = Db::name("proxy")->where("nickname", $nickname)->find();
        if ($data && $data['code'] != $code) {
            return $this->showmsg(0, "昵称已存在", '', '', null);
        }
        //手机号
        if ($mobile) {
            if (!preg_match('/^1\d{10}$/', $mobile)) {
                return $this->showmsg(0, "手机号格式有误", '', '', null);
            }
            $mInfo = Db::name("proxy")->where("bind_mobile", $mobile)->find();
            if ($mInfo && $mInfo['code'] != $code) {
                return $this->showmsg(0, "手机号已存在", '', '', null);
            }
        }

        //分成比例的判断
        if ($percent < $this->rateData['lowest_rate']) {
            return $this->showmsg(0, "提成比例不能小于" . $this->rateData['lowest_rate'], '', '', null);
        }

        //不能比原来小
        if ($percent < $proxyInfo['percent']) {
            return $this->showmsg(0, "设置的提成比例不能小于原有的提成比例", '', '', null);
        }


        if ($proxy['grade'] == 1) {//管理员

            $parentProxy = Db::name("proxy")->where("code", $proxyInfo['parent_id'])->find();
            if ($parentProxy['type'] == 2) {
//                //父级是渠道
//                //渠道下级最高比例
                $qdpercent = $this->rateData['qd_level1rate'];
                //判断父级的比例
                $parentPercent = $parentProxy['percent'];
//                $maxpercent = $parentPercent - 2 >= $qdpercent ? $qdpercent : $parentPercent - 2;
                $maxpercent = $parentProxy['percent'] - 2;
            } else {
                if ($parentProxy['code'] == 1) {
                    //父级是admin
                    $maxpercent = $this->rateData['general_rate'];
                } else {
//                    //父级是普通代理
                    $ptpercent = $this->rateData['level1_rate'];
                    //判断父级的比例
                    $parentPercent = $parentProxy['percent'];
//                    $maxpercent = $parentPercent - 2 >= $ptpercent ? $ptpercent : $parentPercent - 2;
                    $maxpercent = $parentProxy['percent'] - 2;
                }
            }

            if ($percent > $maxpercent) {
                return $this->showmsg(0, "提成比例不能大于" . $maxpercent, '', '', null);
            }

        } else {
            if ($proxy['type'] == 2) {
                //渠道代理
//                if ($proxyInfo['percent'] >= $this->rateData['qd_level1rate']) {//下级代理是否已超出限定值80
//                    if ($percent != $proxyInfo['percent']) {
//                        return $this->showmsg(0, "当前提成比例不能修改", '', '', null);
//                    }
//                } else {
//                    //可设置最大值
//                    //差价2%  2019/06/25新增
//                    $maxPercent = $proxy['percent'] -2  > $this->rateData['qd_level1rate'] ? $this->rateData['qd_level1rate'] : $proxy['percent'] - 2;
//                    if ($percent < $proxyInfo['percent']) {
//                        return $this->showmsg(0, "设置的提成比例不能小于原有的提成比例", '', '', null);
//                    }
//                    if ($percent > $maxPercent) {
//                        return $this->showmsg(0, "设置的提成比例不能大于" . $maxPercent, '', '', null);
//                    }
//                }
                $parentProxy = Db::name("proxy")->where("code", $proxyInfo['parent_id'])->find();
                $maxPercent = $parentProxy['percent'] - 2;
                if ($parentProxy['type'] == 2) {
//                    $maxPercent = $parentProxy['percent'] - 2 >= $this->rateData['qd_level1rate'] ? $this->rateData['qd_level1rate'] : $parentProxy['percent'] - 2;

                } else {
//                    $maxPercent = $parentProxy['percent'] - 2 >= $this->rateData['level1_rate'] ? $this->rateData['level1_rate'] : $parentProxy['percent'] - 2;
                }
//                $maxPercent = $parentProxy['percent'] - 2;
//                if ($percent < $proxyInfo['percent']) {
//                    return $this->showmsg(0, "设置的提成比例不能小于原有的提成比例", '', '', null);
//                }
                if ($percent > $maxPercent) {
                    return $this->showmsg(0, "设置的提成比例不能大于" . $maxPercent, '', '', null);
                }
            } else {
                //普通代理

                //可设置最大值
                //差价2%  2019/06/25新增
                $maxPercent = $proxy['percent'] - 2 >= $this->rateData['level1_rate'] ? $this->rateData['level1_rate'] : $proxy['percent'] - 2;
//                $maxPercent = $proxy['percent'] - 2;
//                if ($percent < $proxyInfo['percent']) {
//                    return $this->showmsg(0, "设置的提成比例不能小于原有的提成比例", '', '', null);
//                }
                if ($percent > $maxPercent) {
                    return $this->showmsg(0, "设置的提成比例不能大于" . $maxPercent, '', '', null);
                }

            }
        }

        $update = [
            'nickname' => $nickname,
            'percent'  => $percent
        ];
        if ($passord) {
            $salt               = strtolower(generateSalt());
            $update['password'] = md5($salt . $passord);
            $update['salt']     = $salt;
        }

        if ($mobile) {
            $update['bind_mobile'] = $mobile;
        }

        if ($remark) {
            $update['descript'] = $remark;
        }
        $res = Db::name("proxy")->where('code', $code)->update($update);
        if ($res) {
            return $this->showmsg(1, "更新成功", '', '', null);
        } else {
            return $this->showmsg(1, "更新失败", '', '', null);
        }

    }

//{"code":0,"msg":"昵称重复","error":null,"hasMore":false,"data":null}
    public function addProxy()
    {

        $proxy = session("proxy");
//        $type  = input("type") ? intval(input("type")) : 0;
        $type     = 0;
        $username = input("accountForReg");
        $passord  = input("passwordForReg");
        $nickname = input("nickNameForReg");
        $percent  = input("royaltyRate");
        $remark   = input("remark");
        if ($proxy['grade'] != 1) {
            $type = 0;
        }


        if ($username == '' || $passord == '' || $nickname == '' || $percent == '' || !in_array($type, [0, 1])) {
            return $this->showmsg(0, "参数为空", '', '', null);
        }
        if ($passord && strlen($passord) < 6) {
            return $this->showmsg(0, "密码长度至少为6位", '', '', null);
        }
        $data = Db::name("proxy")->where("username", $username)->count();
        if ($data > 0) {
            return $this->showmsg(0, "用户名已存在", '', '', null);
        }

        $data = Db::name("proxy")->where("nickname", $nickname)->count();
        if ($data > 0) {
            return $this->showmsg(0, "昵称已存在", '', '', null);
        }

        if (!is_numeric($percent)) {
            return $this->msg(0, "比例有误", null);
        }
        //判断分成比例
        $grade = $proxy["grade"];
        if ($percent < $this->rateData['lowest_rate']) {
            return $this->showmsg(0, "提成比例不能小于" . $this->rateData['lowest_rate'], '', '', null);
        }
        if ($grade == 1) { //管理员
//            if ($percent != $this->rateData['general_rate']) {
//                return $this->showmsg(0, "提成比例必须为" . $this->rateData['general_rate'], '', '', null);
//            }
            if ($percent > $this->rateData['general_rate']) {
                return $this->showmsg(0, "提成比例不能超过" . $this->rateData['general_rate'], '', '', null);
            }
        } else {
            if ($proxy['type'] == 2) {
                if ($percent > $this->rateData['qd_level1rate']) {
                    return $this->showmsg(0, "提成比例不能超过" . $this->rateData['qd_level1rate'], '', '', null);
                }
            } else {
                if ($percent > $this->rateData['level1_rate']) {
                    return $this->showmsg(0, "提成比例不能超过" . $this->rateData['level1_rate'], '', '', null);
                }
            }

            //差价2%
            if ($percent > $proxy["percent"] - 2) {
                return $this->showmsg(0, "提成比例不能大于" . ($proxy["percent"] - 2), '', '', null);
            }
        }


        $salt = strtolower(generateSalt());

        $data = array(
            "username"  => $username,
            "salt"      => $salt,
            "password"  => md5($salt . $passord),
            "nickname"  => $nickname,
            "regtime"   => date("Y-m-d H:i:s"),
            "balance"   => 0,
            "lock"      => 0,
            "type"      => $type,
            "historyin" => 0,
            "percent"   => $percent,
            "descript"  => $remark,
            "last_ip"   => get_ip(),
            "grade"     => $grade + 1,
            "parent_id" => $proxy['code']
        );

//        var_dump(Db::name('teamlevel')->where(['proxy_id' => $proxy['code']])->select());
//        die;
        $ret = Db::name("proxy")->insertGetId($data);

        if ($ret) {
            $proxy_code = sprintf("MT%06d", $ret);
            Db::name("proxy")->where('id', $ret)->update(["code" => $proxy_code]);

            //新增代理等级关系

            //获取当前用户分销关系
            $fxList      = Db::name('teamlevel')->where(['proxy_id' => $proxy['code']])->select();
            $insertData2 = [['proxy_id' => $proxy_code, 'parent_id' => $proxy['code'], 'level' => 1]];
            //组装插入分销关系表数据
            foreach ($fxList as $fx) {
                $insertData2[] = ['proxy_id' => $proxy_code, 'parent_id' => $fx['parent_id'], 'level' => intval($fx['level']) + 1];
            }
            Db::name('teamlevel')->insertAll($insertData2);


            //生成模板
            //$template = Db::name('template')->where('template_code',1)->find();
            $arrTemplate = Db::name('template')->select();

            $usertemp = new \app\admin\model\UserTemplate();
            $usertemp->Qrcode($proxy_code);
            $qrcode   = './public/upload/Qrcode/' . $proxy_code . '.png';
            $tagetimg = $qrcode;
            thrum($tagetimg, 230, 230, $tagetimg);

            foreach ($arrTemplate as $k => $template) {
                if (!empty($template)) {
                    $cfgname = $username . $template['template_code'];


                    // str_replace("/public/","./",$qrcode);
                    $bigimg = "." . $template["template_image"];//str_replace("/public/","./",$template["template_image"]);

                    $qrcode_url = "./public/upload/Qrcode/proxy_" . $proxy_code . $template['template_code'] . '.png';
                    $ret        = combinePic($bigimg, $tagetimg, $template["x"], $template["y"], $qrcode_url);
                    Log::write($ret . ':生成二维码状态', "ERROR");
                    $qrcode2 = '/public/upload/Qrcode/proxy_' . $proxy_code . $template['template_code'] . '.png';


                    $template = array("proxy_id"      => $proxy_code, "config_name" => $cfgname, "template_code" => $template['template_code'],
                                      "template_name" => $template['template_name'], "qrcode" => $qrcode2, "image_url" => $qrcode2,
                                      "down_url"      => $template['distribute_url'] . $proxy_code, "descript" => '');


                    if (config('useshorturl') === true) {
                        $template['down_url'] = file_get_contents(config('shorturl') . $proxy_code) ? file_get_contents(config('shorturl') . $proxy_code) : '';
                    }

                    $status = Db::name("user_template")->insert($template);
                } else {
                    //return $this->msg(0, "模板不存在", null);
                }
            }
            return $this->msg(1, "提交成功", null);

        } else {
            return $this->msg(0, "提交失败", null);
        }
    }


    public function Changepwd()
    {
        return $this->fetch();
    }


    public function retrievepassword()
    {
        $proxyid = session("proxy_id");
        $proxy   = Db::name('proxy')->where('code', $proxyid)->find();
        if ($proxy) {
            $this->assign("mobile", $proxy['bind_mobile']);
        }
        return $this->fetch();
    }


    public function checkpass()
    {
        return $this->fetch();
    }


    /*
     * 修改支付账户信息
     */
    public function checkaccount()
    {
        $proxy    = session("proxy");
        $data     = Db::name("proxy")->where('code', $proxy['code'])->find();
        $bankinfo = Db::name("bankinfo")->where("proxy_id", $proxy['code'])->find();
        $this->assign("proxy", $data);
        $this->assign("bankinfo", $bankinfo);
        return $this->fetch();
    }


    public function bindmobile()
    {
        $proxy_id = session("proxy_id");
        $data     = Db::name("proxy")->field("code,bind_mobile")->where('code', $proxy_id)->find();
        if (empty($data['bind_mobile'])) {
            return $this->fetch();
        } else {
            $this->assign("phone", $data["bind_mobile"]);
            return $this->fetch("proxy/unbindmobile");
        }
    }


    /*
     * 绑定手机号
     */
    public function bindMobileSave()
    {
        $mobile = input("phone");
        $code   = input("code");
        if (empty($mobile) || empty($code)) {
            return false;
        }

        $total = Db::name("proxy")->where("bind_mobile", $mobile)->count();
        if ($total > 0) {
            return $this->msg(0, "该手机号码已经存在，请更换号码绑定！", null);
        }

        $sms      = new Sms();
        $smsmodel = config('smsmodel');
        if ($smsmodel == "self") {
            $status = $sms->checkcode($mobile, $code, 'bindmobile');
        } else if ($smsmodel == "hff") {
            $ret    = $sms->validateSms($mobile, $code);
            $status = $ret->code == 0 ? true : false;
        }
        if (!$status) {
            return $this->msg(0, "短信验证码不正确，请重新输入", null);
        } else {

            $proxy = session("proxy");
            $data  = array("bind_mobile" => $mobile);
            $ret   = Db::name("proxy")->where("code", $proxy['code'])->update($data);
            if ($ret) {
                return $this->msg(1, "绑定成功", null);
            } else {
                return $this->msg(0, "更新失败，请重新输入", null);

            }
        }

    }


    /*
     * 解绑手机号
     */
    public function unbindmobilesave()
    {
        $mobile = input("phone");
        $code   = input("code");
        if (empty($mobile) || empty($code)) {
            return false;
        }
        $sms     = new Sms();
        $smstype = config("smsmodel");
        if ($smstype == "self") {
            $status = $sms->checkcode($mobile, $code, 'bindmobile');
        } else if ($smstype == "hff") {
            $ret = $sms->validateSms($mobile, $code);
            if ($ret->code == 0) {
                $status = true;
            } else {
                $status = false;
            }
        }
        if (!$status) {
            return $this->msg(0, "短信验证码不正确，请重新输入", null);
        }

        if ($status) {
            $proxy = session("proxy");
            $data  = array("bind_mobile" => '');
            $ret   = Db::name("proxy")->where("code", $proxy['code'])->update($data);
            if ($ret) {
                return $this->msg(1, "解绑成功", null);
            } else {
                return $this->msg(0, "解绑失败，请重试", null);

            }
        }

    }


    /*
     * 修改账号密码
     */
    public function modifierPassword()
    {
        $oldPwd     = input('oldPassword');
        $newPwd     = input("password");
        $confirmPwd = input('passwordConfirm');

        $proxy     = session("proxy");
        $decrytPwd = md5($proxy['salt'] . $oldPwd);
        if ($decrytPwd === $proxy['password']) {
            $newPwd = md5($proxy['salt'] . $newPwd);
            $data   = array('password' => $newPwd);
            Db::name("proxy")->where('id', $proxy['id'])->update($data);
            session('proxy', Db::name("proxy")->where('id', $proxy['id'])->find());
            return true;
        } else {
            return false;
        }
    }


    ///修改结算密码
    public function modifycheckpass()
    {
        $oldPwd     = input('oldPassword');
        $newPwd     = input("password");
        $confirmPwd = input('passwordConfirm');

        $proxy     = session("proxy");
        $decrytPwd = md5($proxy['settle_salt'] . $oldPwd);
        if ($decrytPwd === $proxy['password']) {
            $newPwd = md5($proxy['settle_salt'] . $newPwd);
            $data   = array('check_pass' => $newPwd);
            Db::name("proxy")->where('id', $proxy['id'])->update($data);
            session('proxy', Db::name("proxy")->where('id', $proxy['id'])->find());
            return true;
        } else {
            return false;
        }
    }


    /*
     * 检查验证码发送短信
     */
    public function checkmobile()
    {

        $phone   = input("phone");
        $captcha = input("captcha");

        if (empty($phone) || empty($captcha)) {
            return $this->showmsg(0, "必须输入手机号码与图片验证码", "", false, null);
        }
        if (!$this->check($captcha, "bindphone")) {
            return $this->showmsg(0, "输入图片验证码不正确", "", false, null);
        }
        return false;
    }

    public function check($code = '', $id = '')
    {
        if (trim($code) != '') {
            $captcha = new Captcha();
            return $captcha->check($code, $id);
        } else {
            return false;
        }

    }


//    public function sendcode()
//    {
//        $mobile = input("mobile");
//        if(!$mobile){
//            return $this->showmsg(0, "参数错误", "", false, null);
//        }
//
//        $sms =new Sms();
//        $smsmodel = config("smsmodel");
//        $code =rand(1000,9999);
//        if($smsmodel=="self"){
//            $ret = $sms->checkandsend($mobile,$code,'bindmobile',get_ip());
//        }
//        else if($smsmodel=="hff")
//        {
//
//        }
//
//        if(!$ret){
//            return $this->showmsg(0, "短信发送频率过快", "", false, null);
//        }
//        return $this->showmsg(1, "短信发送成功", "", false, null);
//    }


    public function checkresetpassword()
    {
        $code   = input("code");
        $mobile = input("codeMsg");

        if (empty($code) || empty($mobile)) {
            return $this->showmsg(0, "参数错误", "", false, null);
        }

        $sms    = new Sms();
        $status = $sms->validateSms($mobile, $code);//$sms->checkcode($mobile,$code,'resetpwd');
        //print_r($status);
        $status->code = 0;
        if ($status->code == 0) {
            session("resetcheckpass", "isPass");
            return json(['code' => 1, 'msg' => "checkpasssave"]);
        } else {
            return $this->error("验证码不正确");
        }
    }

    public function checkpasssave()
    {
        return $this->fetch();
    }


    public function resetSettlementPassword()
    {
        $passord = input("password");
        $flag    = session("resetcheckpass");
        $salt    = strtolower(generateSalt());
        if (isset($flag)) {
            $proxy     = session("proxy");
            $checkpass = md5($salt . $passord);
            $data      = [
                'check_pass'  => $checkpass,
                'settle_salt' => $salt,
                "updatetime"  => time()
            ];
            $status    = Db::name("proxy")->where(['code' => $proxy['code']])->update($data);

            if ($status) {
                session('proxy', Db::name("proxy")->where('id', $proxy['id'])->find());
                return $this->success("验证密码修改成功", url("index/main"));
            } else {
                return $this->error("验证密码修改失败，请重试");
            }
        }
    }


    /**
     * 代理上下级关系查询
     */
    public function proxyLevel()
    {
        $adminid = session("proxy_id");
        if ($adminid != '1') {
            exit;
        }


        if (Request::instance()->isAjax()) {
            $proxyId  = input('proxy_id');
            $nickname = input('nickname');
            $type     = intval(input('type'));
            $page     = intval(input('page'));
            $limit    = intval(input('limit'));
            $data     = ['code' => 0, 'msg' => '', 'data' => [], 'count' => 0];
            if (!$proxyId && !$nickname) {
                $data['code'] = 1;
                $data['msg']  = '请输入正确的参数';
                return json($data);
            }
            $idList = [];
//            if ($nickname) {
//                $idList = Db::name('proxy')->field('code')->where(['nickname' => ['like', '%'.$nickname.'%']])->select();
//                //$idList = Db::name('proxy')->field('code')->where(['nickname' => $nickname])->select();
//                $idList = array_column($idList, 'code');
//            }
            $where = [];
            if ($type == 1) {//查上级
//                if ($idList) {
//                    $where['code'] =  ['in', $idList];
//                }
                if ($proxyId) {
                    $where['code'] = $proxyId;
                }
                $pidArr        = Db::name('proxy')->field('parent_id')->where($where)->select();
                $pidArr        = array_column($pidArr, 'parent_id');
                $count         = Db::name('proxy')->where(['code' => ['in', $pidArr]])->count();
                $res           = Db::name('proxy')->where(['code' => ['in', $pidArr]])->page($page, $limit)->select();
                $data['count'] = $count;
                $data['data']  = $res;
            } else {//查下级
//                if ($idList) {
//                    $where['parent_id'] =  ['in', $idList];
//                }
                if ($proxyId) {
                    $where['parent_id'] = $proxyId;
                }
                $pidArr        = Db::name('proxy')->field('code')->where($where)->select();
                $pidArr        = array_column($pidArr, 'code');
                $count         = Db::name('proxy')->where(['code' => ['in', $pidArr]])->count();
                $res           = Db::name('proxy')->where(['code' => ['in', $pidArr]])->page($page, $limit)->select();
                $data['count'] = $count;
                $data['data']  = $res;
            }
            return json($data);
        } else {
            return $this->fetch();
        }

    }


}