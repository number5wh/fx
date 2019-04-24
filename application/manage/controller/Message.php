<?php
namespace app\manage\controller;

use app\manage\controller\Base;
use think\Db;
use think\model;
use think\Paginator;

class Message extends Base{
    public function _initialize() {
        $roleid = session("RoleId");
        if($roleid!=1){
            $this->error('您没有权限','index/index');
        }
    }

    public function index(){
        //赋值分页输出
        //$this->assign("status",$status);
        return $this->fetch();
    }

    public function getIndex()
    {
        $title = input("title");
        $page  = intval(input('page')) > 0 ? intval(input('page')) : 1;
        $limit = intval(input('limit')) > 0 ? intval(input('limit')) : 10;


        $where= array("isDel"=>0);
        if(!empty($title)){
            $where['title'] =array('like','%'.$title.'%');
        }

        $messagelist=Model("message");

        //查询满足要求的总的记录数
        $count = $messagelist->where($where)->count();
        $list=$messagelist->where($where)->order('addtime desc ')->page($page,$limit)->select();
        foreach ($list as &$v) {
            $v['addtime'] = date('Y-m-d H:i:s', $v['addtime']);
        }
        unset($v);
        return json(['code'=>0,'data'=>$list, 'count'=>$count]);
    }


    public function delNotice()
    {
        $id =input("id");

        if(!is_numeric($id)){
            return $this->msg(1,"参数错误",null);
        }

        $status = Db::name("message")->where("id",$id)->delete();

        if($status){
            return $this->msg(0,"已删除成功",null);
        }
        else
        {
            return $this->msg(1,"删除失败，请重试！",null);
        }

    }


    public function addNotice(){

        $title = input("titleForAdd");
        $content = input("contentForAdd");

        if(trim($title) || trim($content)){
            $data = array(
                "title"=>$title,
                "content"=>$content,
                "addtime"=>time(),
                "isDel"=>0
            );
            $ret = Db::name("message")->insertGetId($data);
            if($ret>0){
                return $this->msg(0,"添加成功",null);
            }
            else
            {
                return $this->msg(1,"添加失败，请重试",null);
            }
        }
        else
        {
            return $this->msg(1,"参数不能为空",null);
        }
    }


    public function getMsgDetail(){
        $id = input("id");
        if(empty($id)){
            return '';
        }
        else
        {
            $data = Db::name('message')->where('id',$id)->find();
            return json($data);
        }

    }



}


?>