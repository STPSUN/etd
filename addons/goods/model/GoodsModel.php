<?php

namespace addons\goods\model;

class GoodsModel extends \web\common\model\BaseModel {
    
    protected function _initialize() {
        $this->tableName = 'goods';
    }
    
    /**
     * 根据id获取礼品
     * @param type $id
     * @return type
     */
    public function getGoodsByID($id){
        $where['id'] = $id;
        return $this->where($where)->find();
    }
    
    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id desc') {
        $m = new \addons\config\model\Coins();
        $sql = 'select a.*,b.coin_name from ' . $this->getTableName() . ' a,'.$m->getTableName().' b where a.coin_id=b.id';
        if (!empty($filter))
            $sql .=  ' and '.$filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }
    
    
    
}