<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/9
 * Time: 17:02
 */

namespace addons\equity\user\controller;


class RuleConfig extends \web\user\controller\AddonUserBase
{
    public function index()
    {
        return $this->fetch();
    }

    public function add()
    {
        if(IS_POST)
        {
            $data = $_POST;
            $m = new \addons\equity\model\PrivatePlacementRule();
//            $dateRange = explode('-',$data['date_range']);
//            $data['begin_date'] = $dateRange[0];
//            $data['end_date'] = $dateRange[1];
            $data['update_time'] = NOW_DATETIME;
//            $nowDate = date('Y-m-d');
//            if(strtotime($dateRange[0]) >= strtotime($nowDate))
//            {
//                $data['status'] = 1;
//            }
            $res = $m->save($data);
            if($res > 0)
                return $this->successData();
            else
                return $this->failData('失败');
        }else
        {
            $this->assign('id',0);
            return $this->fetch('add');
        }
    }

    public function loadList()
    {
        $m = new \addons\equity\model\PrivatePlacementRule();
        $total = $m->getTotal();
        $rows = $m->getDataList($this->getPageIndex(),$this->getPageSize());

        return $this->toDataGrid($total,$rows);
    }

    public function del()
    {
        $id = $this->_post('id');
        if(empty($id))
            return $this->failData('删除失败，参数有误');

        $m = new \addons\equity\model\PrivatePlacementRule();
        try{
            $res = $m->deleteData($id);
            if($res > 0)
                return $this->successData();
            else
                return $this->failData('删除失败');
        }catch (\Exception $e)
        {
            return $this->failData($e->getMessage());
        }
    }

    public function edit()
    {
        $m = new \addons\equity\model\PrivatePlacementRule();
        $id = $this->_get('id');
        if(empty($id))
            return $this->failData('编辑失败，参数错误');

        if(IS_POST)
        {
            $data = $_POST;
//            $dateRange = explode('-',$data['date_range']);
            $data['update_time'] = NOW_DATETIME;
//            $data['begin_date'] = $dateRange[0];
//            $data['end_date'] = $dateRange[1];
            $nowDate = date('Y-m-d');
//            if(strtotime($dateRange[0]) >= strtotime($nowDate))
//            {
//                $data['status'] = 1;
//            }
            $res = $m->save($data,['id' => $id]);
            if($res > 0)
                return $this->successData();
            else
                return $this->failData('编辑失败');
        }else
        {
            $data = $m->where('id',$id)->find();
            $data['begin_date'] = str_replace('-','/',$data['begin_date']);
            $data['end_date'] = str_replace('-','/',$data['end_date']);

            $this->assign('id',$data['id']);
            $this->assign('data',$data);
            return $this->fetch('edit');
        }
    }
}























