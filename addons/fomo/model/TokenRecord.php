<?php

namespace addons\fomo\model;

/**
 * @author shilinqing
 */
class TokenRecord extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'fomo_token_record';
    }
    
    public function getTotalToken($user_id = -1){
        if($user_id > 0){
            $this->where('user_id='.$user_id);
        }
        $data = $this->sum('token');
        if(empty($data)){
            return 0;
        }
        return $data;
    }
    
    public function getDataByUserID($user_id){
       $where['user_id'] = $user_id;
       return $this->where($where)->find();
    }
    
    /**
     * 
     * @param type $user_id
     * @param type $amount
     * @param type $is_sum 是否为增加 , 默认减少
     */
    public function updateTokenBalance($user_id, $amount ,$is_sum = false ){
        $where['user_id'] = $user_id;
        $data = $this->where($where)->find();
        if(empty($data)){
            $data['user_id'] = $user_id;
            $data['token'] = 0;
            $data['before_token'] = 0;
            
        }
        $data['update_time'] = NOW_DATETIME;
        $data['before_token'] = $data['token'];
        if($is_sum){
            //增加
            $data['token'] = $data['token'] + $amount;
        }else{
            $data['token'] = $data['token'] - $amount;
        }
        $res = $this->save($data);
        if (!$res) {
            return false;
        }
        return $data;
    }
    
        
}
