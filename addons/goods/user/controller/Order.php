<?php

namespace addons\goods\user\controller;

/**
 * 用户兑换订单
 */
class Order extends \web\user\controller\AddonUserBase {
    
    public function index(){
        
        return $this->fetch();
    }
    
    public function loadList(){
        $m = new \addons\goods\model\GoodsOrderModel();
        $keyword = $this->_get('keyword');
        $filter = '1=1';
        if ($keyword != null) {
            $filter .= ' and order_code like \'%' . $keyword . '%\'';
        }
        $total = $m->getTotal($filter);
        $rows = $m->getList($this->getPageIndex(), $this->getPageSize(), $filter);
        return $this->toDataGrid($total, $rows);
    }
    
    public function edit(){
        if(IS_POST){
            $data = $_POST;
            $data['order_status'] = 1;
            $data['update_time'] = NOW_DATETIME;
            try{
                $m = new \addons\goods\model\GoodsOrderModel();
                $m->save($data);
                return $this->successData();
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }   
        }else{
            $this->assign('id',$this->_get('id'));
            $this->setLoadDataAction('loadData');
            return $this->fetch(); 
        }
        
    }
    
    public function loadData(){
        $id = $this->_get('id');
        $m = new \addons\goods\model\GoodsOrderModel();
        $data = $m->getDetail($id);
        return $data;
    }
    
    
}