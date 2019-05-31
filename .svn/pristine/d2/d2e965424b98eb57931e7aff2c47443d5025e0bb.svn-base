<?php
namespace app\admin\controller;

use think\Db;
use think\Request;

class Moneylog extends Base
{

    /**
     * 账户变动明细
     */
    public function index()
    {
        if (Request::instance()->isAjax()) {
            $data = [
                'code' => 0,
                'msg' => '',
                'count' => 0,
                'data' => []
            ];
            $page  = (intval(input('page')) > 0) ? intval(input('page')) : 1;
            $limit = (intval(input('limit')) > 0) ? intval(input('limit')) : 10;
            $type = intval(input('type')) >=0 ? intval(input('type')) : -1;//类型 -1全部 0收入 1支出
            $detail = intval(input('detail')) >= 0 ? intval(input('detail')) : -1; //内容 -1全部 0=税收收入 1=结算提现 2=提现退款 3=推广收入
            $start = input('start');//开始时间
            $end = input('end');//开始时间

            $typeArr = [0 => '收入', 1 => '支出'];
            $detailArr = [0 => '税收收入', 1=> '结算提现', 2 => '提现退款', 3=> '推广收入'];

            $proxy = session("proxy");

            $where = ['proxy_id' => $proxy['code']];
            if ($type != -1) {
                $where['type'] = $type;
            }
            if ($detail != -1) {
                $where['detail'] = $detail;
            }
            if ($start) {
                if ($end) {
                    $where['createday'] = [['egt', $start], ['elt', $end]];
                } else {
                    $where['createday'] = ['egt', $start];
                }
            }

            $count = Db::name('moneylog')
                ->where($where)
                ->count();
            $data['count'] = $count;
            if (!$count) {
                return json($data);
            }
            $list = Db::name('moneylog')
                ->where($where)
                ->field('id, type, detail, money, leftmoney, createtime')
                ->page($page, $limit)
                ->order('createtime', 'DESC')
                ->select();
            foreach ($list as &$v) {
                $v['typename'] = $typeArr[$v['type']];
                $v['detailname'] = $detailArr[$v['detail']];
            }
            unset($v);
            $data['data'] = $list;
            return json($data);
        } else {
            $leftmoney = Db::name('proxy')->where(['code' => session('proxy_id')])->field('balance')->find();
            $this->assign('leftmoney',$leftmoney['balance']);
            return $this->fetch();
        }
    }
}
