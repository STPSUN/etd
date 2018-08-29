<?php

namespace addons\miner\model;
/**
 * Description of MinerPool
 * 矿场设置
 * @author shilinqing
 */
class MinerPool extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'minerpool_conf';
    }
    
    
}
