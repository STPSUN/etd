<?php

namespace addons\fomo\model;

/**
 * Description of RewardRecord
 *
 * @author shilinqing
 */
class RewardRecord extends \web\common\model\BaseModel {

    protected function _initialize() {
        $this->tableName = 'fomo_reward_record';
    }

    /**
     * 添加记录
     * @param type $user_id
     * @param type $coin_id
     * @param type $before_amount
     * @param type $amount
     * @param type $after_amount
     * @param type $type
     * @param type $game_id
     */
    public function addRecord($user_id, $coin_id, $before_amount, $amount, $after_amount, $type, $game_id, $remark = '') {
        $data['user_id'] = $user_id;
        $data['coin_id'] = $coin_id;
        $data['amount'] = $amount;
        $data['before_amount'] = $before_amount;
        $data['after_amount'] = $after_amount;
        $data['type'] = $type;
        $data['game_id'] = $game_id;
        $data['remark'] = $remark;
        $data['update_time'] = NOW_DATETIME;
        return $this->add($data);
    }

    public function getUserTotal($user_id, $coin_id, $game_id = 0) {
        $where['user_id'] = $user_id;
        $where['coin_id'] = $coin_id;
        if ($game_id != 0) {
            $where['game_id'] = $game_id;
        }
        $data = $this->where($where)->sum('amount');
        if (empty($data)) {
            return 0;
        }
        return $data;
    }

    /**
     * 根据奖励类型获取总数
     * @param type $user_id
     * @param type $type
     * @return type
     */
    public function getTotalByType($user_id, $coin_id, $type = '3') {
        $where['type'] = array('in', $type);
        $where['user_id'] = $user_id;
        $where['coin_id'] = $coin_id;
        $data = $this->where($where)->sum('amount');
        if (empty($data)) {
            return 0;
        }
        return $data;
    }
    
    public function getGameWinner($game_id){
        $m = new \addons\member\model\MemberAccountModel();
        $sql = 'select a.amount, b.username from '.$this->getTableName().' a , '.$m->getTableName().' b where a.user_id=b.id and a.type=2 and a.game_id='.$game_id;
        return $this->query($sql);
    }
}
