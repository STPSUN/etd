<?php

namespace addons\goods\model;

class GoodsOrderModel extends \web\common\model\BaseModel {
    
    protected function _initialize() {
        $this->tableName = 'goods_order';
    }
    
        
    public function getList($pageIndex = -1, $pageSize = -1, $filter = '', $order = 'id desc') {
        $tagsM = new \addons\goods\model\GoodsModel();
        $userM = new \addons\member\model\MemberAccountModel();
        $filed = 'case a.order_status when 0 then "未发货" when 1 then "已发货" when 2 then "已收货" end as status';
        $sql = 'select a.*,b.goods_name,b.pic,b.num,c.phone,'.$filed.' from ' . $this->getTableName() . ' a,'.$tagsM->getTableName().' b , '.$userM->getTableName().' c where a.goods_id=b.id and c.id=a.user_id';
        if (!empty($filter))
            $sql .=  ' and '.$filter;
        return $this->getDataListBySQL($sql, $pageIndex, $pageSize, $order);
    }
    
    public function confirmOrder($id,$user_id){
        $where['id'] = $id;
        $where['user_id'] = $user_id;
        $where['order_status'] = 1;
        $data['order_status'] = 2;
        return $this->where($where)->update($data);
        
    }
    
}