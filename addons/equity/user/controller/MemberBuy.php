<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/9/12
 * Time: 14:38
 */

namespace addons\equity\user\controller;


class MemberBuy extends \web\user\controller\AddonUserBase
{
    public function index()
    {
        return $this->fetch();
    }

    public function loadList()
    {
        $m = new \addons\member\model\MemberBuy();
        $userM = new \addons\member\model\MemberAccountModel();
        $total = $m->getTotal();
        $rows = $m->getDataList($this->getPageIndex(),$this->getPageSize());

        $data = $this->toDataGrid($total,$rows);
        foreach ($data['rows'] as &$v)
        {
            $user = $userM->getDetail($v['user_id']);
            $v['username'] = $user['username'];
            $v['release_time'] = date('Y-m-d H:i:s',$v['release_time']);
        }

        return $data;
    }

}





















