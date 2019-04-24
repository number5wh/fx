<?php
namespace app\manage\controller;

use think\Db;

class Dayreward extends Base{

    public function _initialize() {
        $roleid = session("RoleId");
        if($roleid!=1){
            $this->error('您没有权限','index/index');
        }
    }
    public function index(){
        return $this->fetch();
    }

    public function getIndex()
    {
        $list = Db::table('dayreward')->select();
        return json(['code' => 0, 'data' => $list]);
    }

    public function edit()
    {
        $id = intval(input('id'));
        $income = intval(input('income'));
        $reward = intval(input('reward'));

        if ($id<=0 || $income<=0 || $reward <=0) {
            return json(['code' => 1, 'msg' => '输入数据有误']);
        }
        if (!Db::table('dayreward')->where('id', $id)->find()) {
            return json(['code' => 2, 'msg' => '记录不存在']);
        }

        $update = Db::table('dayreward')->where('id', $id)->update(['income' => $income, 'reward' => $reward]);
        if (!$update) {
            return json(['code' => 3, 'msg' => '更新失败']);
        }
        return json(['code' => 0, 'msg' => '更新成功']);
    }
    public function add()
    {
        $income = intval(input('add_income'));
        $reward = intval(input('add_reward'));

        if ($income<=0 || $reward <=0) {
            return json(['code' => 1, 'msg' => '输入数据有误']);
        }

        if (Db::table('dayreward')->where('income', $income)->count()) {
            return json(['code' => 2, 'msg' => '不可重复添加相同比例的数据']);
        }

        $update = Db::table('dayreward')->insertGetId(['income' => $income, 'reward' => $reward]);
        if (!$update) {
            return json(['code' => 3, 'msg' => '新增失败']);
        }
        return json(['code' => 0, 'msg' => '新增成功']);
    }


    public function delete()
    {
        $id = intval(input('id'));
        if ($id<=0) {
            return json(['code' => 1, 'msg' => '输入数据有误']);
        }
        $update = Db::table('dayreward')->delete($id);
        if (!$update) {
            return json(['code' => 2, 'msg' => '删除失败']);
        }
        return json(['code' => 0, 'msg' => '删除成功']);
    }
}