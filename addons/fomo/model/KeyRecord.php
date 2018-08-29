<?php

namespace addons\fomo\model;

/**
 * @author shilinqing
 */
class KeyRecord extends \web\common\model\BaseModel{
    
    protected function _initialize() {
        $this->tableName = 'fomo_key_record';
    }
    
    public function saveUserKey($user_id,$team_id,$game_id, $key_num){
        $where['user_id'] = $user_id;
        $where['team_id'] = $team_id;
        $where['game_id'] = $game_id;
        $data = $this->where($where)->find();
        if(!empty($data)){
            $data['before_num'] = $data['key_num'];
            $data['key_num'] = $data['key_num'] + $key_num;
            $data['update_time'] = NOW_DATETIME;
            return $this->save($data);
        }else{
            $data['team_id'] = $team_id;
            $data['game_id'] = $game_id;
            $data['user_id'] = $user_id;
            $data['key_num'] = $key_num;
            $data['update_time'] = NOW_DATETIME;
            return $this->add($data);
        }
    }
    
    /**
     * 获取当场游戏用户key总量
     * @param type $user_id
     * @param type $game_id
     * @return type
     */
    public function getTotalByGameID($user_id,$game_id){
        $where['user_id'] = $user_id;
        $where['game_id'] = $game_id;
        $data = $this->where($where)->sum('key_num');
        if(empty($data)){
            return 0;
        }
        return $data;
    }

    /**
     * 获取user_id 以外的所有指定游戏,战队 - 拥有key的用户
     * @param type $user_id
     * @param type $game_id 
     * @param type $team_id
     */
    public function getRecordWithOutUserID($user_id, $game_id, $team_id){
        $where['game_id'] = $game_id;
        $where['team_id'] = $team_id;
        $where['user_id'] = array('<>', $user_id);
        return $this->where($where)->field('id,user_id,key_num')->select();
    }
    
    /**
     * 获取指定游戏,战队的key的总数
     * @param type $game_id
     * @param type $team_id
     * @return int
     */
    public function getTotalByGameAndTeamID($game_id, $team_id){
        $where['game_id'] = $game_id;
        $where['team_id'] = $team_id;
        $data = $this->where($where)->sum('key_num');
        if (empty($data)) {
            return 0;
        }
        return $data;
    }
    
    /**
     * 获取最后一个投注者
     */
    public function getLastWinner($game_id){
        $where['game_id'] = $game_id;
        $where['is_winner'] = 1;
        $data = $this->where($where)->field('id,team_id,user_id')->order('update_time desc')->find();
        if(empty($data)){
            //没有指定赢家
            unset($where['is_winner']);
            $data = $this->where($where)->field('id,team_id,user_id')->order('update_time desc')->find();
        }
        return $data;
        
    }
  
}
