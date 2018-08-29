<?php

namespace addons\goods\user\controller;

/**
 * 商城
 */
class Index extends \web\user\controller\AddonUserBase {

    public function index(){
       return $this->fetch(); 
    }
    
    public function edit(){
        $m = new \addons\goods\model\GoodsModel();
        if(IS_POST){
            $data = $_POST;
            $data['update_time'] = NOW_DATETIME;
            $id = $data['id'];
            try{
                if(empty($id)){
                    $data['stock'] = $data['total_num'];
                    $m->add($data);
                }else
                    $m->save($data);
                return $this->successData();
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }   
        }else{
            $this->assign('id',$this->_get('id'));
            $this->assign('order_index', $m->getNewOrderIndex()); //排序
            $m = new \addons\config\model\Coins();
            $data = $m->getDataList(-1,-1,'','','id asc');
            $this->assign('coin_data', $data);
            $this->setLoadDataAction('loadData');
            return $this->fetch(); 
        }
        
    }
    
    /**
     * 设置库存
     * @return type
     */
    public function edit_stock(){
        if(IS_POST){
            try{
                $data['stock'] = floatval($this->_post('stock'));
                $data['id'] = $this->_post('id');
                $m = new \addons\goods\model\GoodsModel();
                $m->save($data);
                return $this->successData();
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage()); 
            }
        }else{
            $this->assign('id',$this->_get('id'));
            $this->setLoadDataAction('loadStock');
            return $this->fetch();
        }
    }
    
    public function loadStock(){
        $m = new \addons\goods\model\GoodsModel();
        $id = $this->_get('id');
        $data = $m->getDetail($id);
        return $data;
        
    }
    
    public function loadList(){
        $m = new \addons\goods\model\GoodsModel();
        $keyword = $this->_get('keyword');
        $filter = '1=1';
        if ($keyword != null) {
            $filter .= ' and goods_name like \'%' . $keyword . '%\'';
        }
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter, '', $this->getOrderBy('id asc'));
        return $this->toDataGrid($total, $rows);
    }
    
    public function loadData(){
        $id = $this->_get('id');
        $m = new \addons\goods\model\GoodsModel();
        $data = $m->getDetail($id);
        return $data;
    }
    
    
    public function del(){
        $id = $this->_post('id');
        $m = new \addons\goods\model\GoodsModel();
        $res = $m->deleteData($id);
        if($res >0){
            return $this->successData();
        }else{
            return $this->failData('删除失败');
        }
    }
    
    
}