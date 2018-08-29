<?php

namespace addons\miner\model;
/**
 * Description of MinerConfig
 * 矿场设置
 * @author zmh
 */
class MinerConfig extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'miner_conf';
    }
	
	//设置默认    
    public function changeDefault($id,$status){
    	$map['id'] = $id;
    	$data['is_default'] = $status;
    	return $this->where($map)->update($data);
    }

}
