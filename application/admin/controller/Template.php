<?php

namespace app\admin\controller;

use app\admin\controller\Base;
use think\Db;
use think\model;
use think\Paginator;

class Template extends Base
{

    public function index()
    {
        return $this->fetch();
    }

    public function getIndex()
    {
        $page  = intval(input('page')) > 0 ? intval(input('page')) : 1;
        $limit = intval(input('limit')) > 0 ? intval(input('limit')) : 10;
        $proxy_id     = session("proxy_id");
        $where        = array("proxy_id" => $proxy_id);
        $templatelist = Model("UserTemplate");
        //查询满足要求的总的记录数
        $list = $templatelist->where($where)->order('id desc ')->page($page, $limit)->select();
        $count = $templatelist->where($where)->count();
        return json(['code' => 0, 'data' => $list, 'count' => $count]);
    }


    public function getTemplate()
    {
        $data = Db::name("template")->where('isdel', 0)->select();
        $ret  = array();

        foreach ($data as $key => $val) {
            $ret[$key]['id']    = $val["id"];
            $ret[$key]['num']   = $val["template_code"];
            $ret[$key]['path']  = $val["template_image"];
            $ret[$key]['x']     = $val["x"];
            $ret[$key]['y']     = $val["y"];
            $ret[$key]['isDel'] = $val["isdel"];
        }
        return json(array('code'=>0, 'data'=>$ret));
    }


    public function checkDownload()
    {
        //print_r($this->request);
        return false;
    }


    public function download()
    {
        $tempid = input("id");
        if (!is_numeric($tempid)) {
            return $this->msg(1, "参数错误", null);
        }
        $template = Db::name("template")->where("template_code", $tempid)->find();
        if ($template) {
            $proxy_id = session("proxy_id");
            $target   = $filename = "./public/upload/Qrcode/" . $proxy_id . ".png";
            $filename = "./public/upload/Qrcode/proxy_" . $proxy_id . $tempid . ".png";
            $source   = "./" . $template["template_image"];//str_replace("/public/",);
            combinePic($source, $target, $template["x"], $template["y"], $filename);
            header('Content-Disposition:attachment;filename=' . basename($filename));
            header('Content-Length:' . filesize($filename));
//读取文件并写入到输出缓冲
            readfile($filename);
            exit();
        }

    }


    public function resetpromotionsetting()
    {

        $url = '';
        $usertemp = new \app\admin\model\UserTemplate();
        if (config('useshorturl') === true) {
            $url = $usertemp->getShortUrl(session("proxy_id"));
        }


        for ($templateId = 1; $templateId<=6; $templateId++) {

            $usertemp->Qrcode(session("proxy_id"), $url);
            $template = Db::name("template")->where("template_code",$templateId)->find();
            $proxy_id = session("proxy_id");

            $target =$filename ="./public/upload/Qrcode/".$proxy_id.".png";
            thrum($target, 230,230, $target);
            if($template){
                $filename ="./public/upload/Qrcode/proxy_".$proxy_id."_".$templateId.".png";
                $source = "./".$template["template_image"];//str_replace("/public/",);
                combinePic($source,$target,$template["x"],$template["y"],$filename);
                $qrcode_url =str_replace("./","/",$filename);
                $data = array(
                    "template_code" => $template['template_code'],
                    "template_name" => $template['template_name'],
                    "qrcode" => $qrcode_url,
                    "image_url" => $qrcode_url,
                    "descript" => date('Y-m-d H:i:s').'生成',
                    "down_url" => $template['distribute_url'] . $proxy_id
                );
                //使用微信地址
                if (config('useshorturl') === true) {
                    $data['down_url'] =  $url;
                }
                $find = Db::name("user_template")->where("proxy_id",$proxy_id)->where('template_code', $templateId)->find();
                if ($find) {
                    $status = Db::name("user_template")->where("proxy_id",$proxy_id)->where('template_code', $templateId)->update($data);
                } else {
                    $data['config_name'] = session('proxy')['nickname'].$template['template_code'];
                    $data['proxy_id'] = $proxy_id;
                    $status = Db::name("user_template")->insertGetId($data);
                }

            }
        }

        if($status){
            return $this->msg(1,'生成成功',null);
        }
        else
        {
            return $this->msg(0,'生成失败',null);
        }


    }
}