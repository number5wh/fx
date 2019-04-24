<?php

namespace app\admin\controller;

use think\Controller;
use think\Session;
use think\Db;
use think\validate;
use Hprose;

class  Company  extends  Controller{

public function getPlayerList()
{

    $proxylist = Db::name("proxy")->where("islock","0")->limit(1)->select();


    $company = new \app\admin\model\Company();
    $ret = $company->getPlayer("HFH0000023");
    print_r($ret);exit();

    foreach($proxylist as $k=>$v){
        $proxy_id = $v["code"];
        echo $proxy_id.'<br/>';

        $company = new \app\admin\model\Company();
        $ret = $company->getPlayer($proxy_id);
        print_r($ret);
        sleep(2000);
    }


}


public function getBillList(){

    $proxylist = Db::name("proxy")->where("islock","0")->select();

    $company = new \app\admin\model\Company();
    $ret = $company->getBillList('20120',1,10000);
    print_r($ret);

//    foreach($proxylist as $k=>$v){
//        $proxy_id = $v["code"];
//        //echo $proxy_id.'<br/>';
//
//        $company = new \app\admin\model\Company();
//        $ret = $company->getBillList($proxy_id,1,10000);
//        print_r($ret);
//        sleep(2000);
//    }



}




}