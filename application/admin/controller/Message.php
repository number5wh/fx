<?php
namespace app\admin\controller;

use app\admin\controller\Base;
use think\Db;
use think\model;
use think\Paginator;

class Message extends Base{

    public function index(){

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
        $list=$messagelist->where($where)->order('addtime desc ')->page($page, $limit)->select();
        foreach ($list as &$v) {
            $v['addtime'] = date('Y-m-d H:i:s', $v['addtime']);
        }
        unset($v);
        return json(['code' => 0, 'count' => $count, 'data' => $list]);
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