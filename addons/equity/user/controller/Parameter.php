<?php

/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/8
 * Time: 11:40
 */

namespace addons\equity\user\controller;

use think\Exception;

class Parameter extends \web\user\controller\AddonUserBase
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
            $m = new \addons\equity\model\PrivatePlacementConf();
            $data['update_time'] = NOW_DATETIME;
            $res = $m->save($data);
            if($res > 0)
            {
                return $this->successData();
            }else
            {
                return $this->failData('失败');
            }
        }else
        {
            $this->assign('id',0);
            return $this->fetch('add');
        }
    }

    public function edit()
    {
        $m = new \addons\equity\model\PrivatePlacementConf();
        $id = $this->_get('id');
        if(IS_POST)
        {
            $data = $_POST;
            $data['update_time'] = NOW_DATETIME;
            switch ($data['pay_type'])
            {
                case 2:
                    $data['qrcode_img'] = '';
                    break;
                case 3:
                    $data['open_bank'] = '';
                    $data['real_name'] = '';
                    $data['qrcode_img'] = '';
                    break;
                default:
                    $data['open_bank'] = '';
                    $data['real_name'] = '';
                    break;
            }
            $res = $m->save($data,['id' => $id]);
            if($res > 0)
            {
                return $this->successData();
            }else
            {
                return $this->failData('编辑失败');
            }
        }else
        {
            $this->assign('id',$id);
            $this->setLoadDataAction('loadData');
            return $this->fetch('edit');
        }
    }

    public function loadList()
    {
        $m = new \addons\equity\model\PrivatePlacementConf();
        $total = $m->getTotal();
        $rows = $m->getDataList($this->getPageIndex(),$this->getPageSize());

        return $this->toDataGrid($total,$rows);
    }

    public function del()
    {
        $id = $this->_post('id');
        if(empty($id))
        {
            return $this->failData('删除失败，参数有误');
        }

        $m = new \addons\equity\model\PrivatePlacementConf();
        try{
            $res = $m->deleteData($id);
            if($res > 0)
            {
                return $this->successData();
            }else
            {
                return $this->failData('删除失败');
            }
        }catch (\Exception $e)
        {
            return $this->failData($e->getMessage());
        }
    }

    public function loadData(){
        $id = $this->_get('id');
        $m = new \addons\equity\model\PrivatePlacementConf();
        $data = $m->getDetail($id);
        return $data;
    }
}






















