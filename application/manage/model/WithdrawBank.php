<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/8/22
 * Time: 22:14
 */
namespace app\manage\model;
use think\Db;
use think\Model;

class WithdrawBank extends Model{
    //新增银行卡接口
//    private $_key1 = 'TMINI04A1167';
//    private $_key2 = 'TMINI04A0948';
    private $_key3 = '7d28c6097b31a1ee4dd8c2f396b325940b27372448a1a5817242c0e6';
    private $_company = 'motian001';
    private $_url = 'https://www.tly-transfer.com/sfisapi/';
    public function  addCard($cardNum, $name, $bank,$city,$province)
    {
        $data = [];
        $data['module'] = 'bankcard';
        $data['method'] = 'api_add_member_card';
        $data['company_name'] = $this->_company;
        $data['payload'] = [
            'card_number' => $cardNum,
            'real_name' => $name,
            'bank_flag' => $bank,
            'trans_mode' => 'out_trans',
            'bank_city' => $city,
            'bank_provinces' => $province,
            'bank_area' => '中国'
        ];

        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sign = base64_encode(hash_hmac('sha256', $data, $this->_key3,true));
        $headers = [
            'TLYHMAC:'. $sign
        ];

//        var_dump(http_build_query($data));
//        die;
        //curl请求
        $res = $this->curlpost($data, $headers);
        return $res;
    }

    //改银行卡
    public function  modifyCard($cardNum, $name, $bank,$city,$province)
    {
        $data = [];
        $data['module'] = 'bankcard';
        $data['method'] = 'api_update_card_info';
        $data['company_name'] = $this->_company;
        $data['payload'] = [
            'card_number' => $cardNum,
            'real_name' => $name,
            'bank_flag' => $bank,
            'city' => $city,
            'province' => $province,
        ];

        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sign = base64_encode(hash_hmac('sha256', $data, $this->_key3,true));
        $headers = [
            'TLYHMAC:'. $sign
        ];

//        var_dump(http_build_query($data));
//        die;
        //curl请求
        $res = $this->curlpost($data, $headers);
        return $res;
    }

    //新加订单
    public function addOrder($cardNum, $amount, $name, $ordernumber)
    {
        $data = [];
        $data['module'] = 'order';
        $data['method'] = 'api_add_order';
        $data['company_name'] = $this->_company;
        $data['payload'] = [
            'card_number' => $cardNum,
            'amount' => $amount,
            'trans_mode' => 'out_trans',
            'real_name' => $name,
            'order_number' => $ordernumber
        ];

//        $strData = '';
//        foreach ($data as $k => $v) {
//            $strData .= $k.'='.$v.'&';
//        }
//        $strData = rtrim($strData, '&');
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sign = base64_encode(hash_hmac('sha256', $data, $this->_key3,true));
        $headers = [
            'TLYHMAC:'. $sign
//            'content_type:'. "content-type", "application/json"
        ];

//        var_dump(http_build_query($data));
//        die;
        //curl请求
        $res = $this->curlpost($data, $headers);
        return $res;
    }


    //回调
//    public function notify($postData, $sign, $get)
//    {
//        $data = [
//            'order_number' => $postData['order_number'],
//            'card_number' => $postData['card_number'],
//            'balance' => $postData['balance'],
//            'status' => $postData['status'],
//            'fees' => $postData['fees'],
//            'amount' => $postData['amount'],
//            'to_card_number' => $postData['to_card_number'],
//            'trans_mode' => $postData['trans_mode'],
//            'company_name' => $postData['company_name'],
//        ];
//
//        $checkSign = base64_encode(hash_hmac('sha256', $get, $this->_key3,true));
//        if ($checkSign != $sign) {
//            echo "sign wrong";
//            save_log('apidata/withdrawnotify',' sign wrong, checksign:'.$checkSign.' sign:'.$sign);
//            exit;
//        }
//
//        //获取前两位
//        $pre = substr($data['order_number'], 0, 2);
//        if ($pre == 'GZ') {//大为那边的
//            $a = file_get_contents("http://dey6kxqf.game2019.net/?d=Game&c=GamePayMent&a=updateOrderStatus&status=".$data['status']."&orderId=".$data['order_number']."&amount=".$data['amount']);
//            exit;
//        }
//
//        //去掉前缀
//        $myorderId = substr($data['order_number'], 2);
//        save_log('apidata/withdrawnotify',$myorderId);
//        $orderInfo = Db::name('checklog')->where(['orderid' => $myorderId])->find();
//
//
////        var_dump($postData, $data,  $sign, $checkSign);
////        die;
//
//        if (!$orderInfo || $orderInfo['status'] != 5) {//不存在订单或状态不为5
//            save_log('apidata/withdrawnotify','no order or status!=5');
//            echo "no order or status no eq 5";
//            exit;
//        }
//
//
//        Db::startTrans();
//        if (strtoupper($data['status']) == 'SUCCESS') {//成功
//
//            Db::name('checklog')->where(['orderid' => $myorderId])->update(['status' => 4, 'info'=>'提现成功','descript' => $orderInfo['proxy_id'].'于'.date('Y-m-d H:i:s'.'提现成功,金额'.$data['amount'].'元')]);
//            Db::name('withdrawbank')->insert([
//                'proxy_id' => $orderInfo['proxy_id'],
//                'card_number' => $data['card_number'],
//                'order_number' => $myorderId,
//                'balance' => $data['balance'],
//                'amount' => $data['amount'],
//                'to_card_number' => $data['to_card_number'],
//                'status' => $data['status'],
//                'fees' => $data['fees'],
//                'real_name' => $orderInfo['name'],
//                'bank' => $orderInfo['bank'],
//                'createtime' => date('Y-m-d H:i:s')
//            ]);
//            echo "true";
//            save_log('apidata/withdrawnotify', ' SUCCESS!!');
//        } else {
//            //失败回退
//            //状态更新
//            Db::name('checklog')->where(['orderid' => $myorderId])->update(['status' => 3, 'descript' => '银行处理未通过']);
//            //金额回退
//            Db::name('proxy')->where("code",$orderInfo['proxy_id'])->update(['balance' =>Db::raw('balance+'.$orderInfo['amount'])]);
//            $money = Db::name("proxy")->where("code", $orderInfo["proxy_id"])->field('balance,historyin')->find();
//            //金额log
//            Db::name('moneylog')->insert([
//                'type' => 0,
//                'detail' => 2,
//                'tax' => 0,
//                'money' => $orderInfo["amount"],
//                'leftmoney' => $money['balance'],
//                'historyin' => $money['historyin'],
//                'proxy_id'  => $orderInfo["proxy_id"],
//                'createtime' => date("Y-m-d H:i:s"),
//                'createday' => date("Ymd")
//            ]);
//            //incomelog
//            $data1= array(
//                "typeid"=>2,
//                "proxy_id"=>$orderInfo['proxy_id'],
//                "changmoney"=>$data['amount'],
//                "descript"=>'银行处理未通过，请确认您的银行卡信息是否正确',
//                "createtime"=>time(),
//                'createday' => date('Ymd')
//            );
//            Db::name("incomelog")->insert($data1);
//            save_log('apidata/withdrawnotify', ' fail');
//            echo 'fail';
//        }
//
//        Db::commit();
//    }

    public function notify($postData)
    {
        $data = [
            'order_number' => $postData['order_number'],
            'card_number' => $postData['card_number'],
            'balance' => $postData['balance'],
            'status' => $postData['status'],
            'fees' => $postData['fees'],
            'amount' => $postData['amount'],
            'to_card_number' => $postData['to_card_number'],
            'trans_mode' => $postData['trans_mode'],
            'company_name' => $postData['company_name'],
            'sign'       => $postData['sign']
        ];
        if ($postData['sign'] != md5("motian".$postData['order_number'])) {
            save_log('apidata/withdrawnotify',"sign wrong:" . md5("motian".$postData['order_number']));
            exit;
        }

        //去掉前缀
        $myorderId = substr($data['order_number'], 2);
        save_log('apidata/withdrawnotify',$myorderId);
        $orderInfo = Db::name('checklog')->where(['orderid' => $myorderId])->find();


        if (!$orderInfo || $orderInfo['status'] != 5) {//不存在订单或状态不为5
            save_log('apidata/withdrawnotify','no order or status!=5');
            echo "no order or status no eq 5";
            exit;
        }


        Db::startTrans();
        if (strtoupper($data['status']) == 'SUCCESS') {//成功

            Db::name('checklog')->where(['orderid' => $myorderId])->update(['status' => 4, 'info'=>'提现成功','descript' => $orderInfo['proxy_id'].'于'.date('Y-m-d H:i:s'.'提现成功,金额'.$data['amount'].'元')]);
            Db::name('withdrawbank')->insert([
                'proxy_id' => $orderInfo['proxy_id'],
                'card_number' => $data['card_number'],
                'order_number' => $myorderId,
                'balance' => $data['balance'],
                'amount' => $data['amount'],
                'to_card_number' => $data['to_card_number'],
                'status' => $data['status'],
                'fees' => $data['fees'],
                'real_name' => $orderInfo['name'],
                'bank' => $orderInfo['bank'],
                'createtime' => date('Y-m-d H:i:s')
            ]);
            echo "true";
            save_log('apidata/withdrawnotify', ' SUCCESS!!');
        } else {
            //失败回退
            //状态更新
            Db::name('checklog')->where(['orderid' => $myorderId])->update(['status' => 3, 'descript' => '银行处理未通过']);
            //金额回退
            Db::name('proxy')->where("code",$orderInfo['proxy_id'])->update(['balance' =>Db::raw('balance+'.$orderInfo['amount'])]);
            $money = Db::name("proxy")->where("code", $orderInfo["proxy_id"])->field('balance,historyin')->find();
            //金额log
            Db::name('moneylog')->insert([
                'type' => 0,
                'detail' => 2,
                'tax' => 0,
                'money' => $orderInfo["amount"],
                'leftmoney' => $money['balance'],
                'historyin' => $money['historyin'],
                'proxy_id'  => $orderInfo["proxy_id"],
                'createtime' => date("Y-m-d H:i:s"),
                'createday' => date("Ymd")
            ]);
            //incomelog
            $data1= array(
                "typeid"=>2,
                "proxy_id"=>$orderInfo['proxy_id'],
                "changmoney"=>$data['amount'],
                "descript"=>'银行处理未通过，请确认您的银行卡信息是否正确',
                "createtime"=>time(),
                'createday' => date('Ymd')
            );
            Db::name("incomelog")->insert($data1);
            save_log('apidata/withdrawnotify', ' fail');
            echo 'fail';
        }

        Db::commit();
    }

    //发送请求操作仅供参考,不为最佳实践
    public function curlpost($params, $header)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
//        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//如果不加验证,就设false,商户自行处理
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $output = curl_exec($ch);
        curl_close($ch);
        return  $output;
    }

}