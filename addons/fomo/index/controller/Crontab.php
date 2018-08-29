<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace addons\fomo\index\controller;

/**
 * Description of Crontab
 * f3d投注分配限制所选战队, p3d为所有
 * 类型：0=p3d分红,1=f3d分红
 * @author shilinqing
 */
class Crontab extends \web\common\controller\Controller{
    
    public function _initialize(){
        
    }
    
    public function excute(){
        $queueM = new \addons\fomo\model\BonusSequeue();
        $data = $queueM->getUnSendData();
        if(!empty($data)){
            try{
                $queueM->startTrans();
                if($data['type'] == 1){
                    //f3d分红,去除用户本身, 只分战队成员
                    $res = $this->sendF3d($data['user_id'],$data['coin_id'], $data['game_id'], $data['team_id'],$data['amount'],$data['scene']);
                   
                }else{
                    //p3d分红
                    $res = $this->sendP3d($data['user_id'],$data['coin_id'], $data['amount'],$data['game_id'],$data['scene']);
                }
                if($res){
                    //更新发放状态
                    $data['status'] = 1;
                    $data['update_time'] = NOW_DATETIME;
                    $queueM->save($data);
                    $queueM->commit();
                    echo $this->successData();
                }else{
                    echo $this->failData('发放失败');
                }
            } catch (\Exception $ex) {
                $queueM->rollback();
                echo $this->failData($ex->getMessage());
            }
        }
    }
    
    /**
     * 发放F3d分红
     * @param type $user_id
     * @param type $coin_id
     * @param type $team_id
     * @param type $game_id
     * @param type $amount
     * @param type $scene 场景id 场景:0=p3d购买，1=f3d投注分配，2=f3d开奖分配'
     */
    private function sendF3d($user_id, $coin_id, $game_id, $team_id, $amount, $scene){
        $keyRecordM = new \addons\fomo\model\KeyRecord();
        $balanceM = new \addons\member\model\Balance();
        $rewardM = new \addons\fomo\model\RewardRecord();
        //查询指定游戏,指定战队成员拥有key的所有user
        $record_list = $keyRecordM->getRecordWithOutUserID($user_id, $game_id, $team_id);
        if(!empty($record_list)){
            $team_total_key = $keyRecordM->getTotalByGameAndTeamID($game_id, $team_id);
            foreach($record_list as $k => $record){
                $user_id = $record['user_id'];
                $key_num = $record['key_num'];
                $rate = $this->getUserRate($team_total_key, $key_num);
                $_amount = $amount * $rate;
                //添加余额, 添加分红记录
                $balance = $balanceM->getBalanceByCoinID($user_id, $coin_id);
                $balance = $balanceM->updateBalance($user_id, $_amount, $coin_id, true);
                if($balance != false){
                    $before_amount = $balance['before_amount'];
                    $after_amount = $balance['amount'];
                    $type = 0; //奖励类型 0=投注分红，1=胜利战队分红，2=胜利者分红，3=邀请分红
                    $remark = 'f3d投注分红';
                    $rewardM->addRecord($user_id, $coin_id, $before_amount, $_amount, $after_amount, $type, $game_id,$remark);
                }
            }
        }
        return true;
    }
    
    /**
     * 发放P3d分红 
     * @param type $coin_id
     * @param type $amount
     * @param type $game_id
     * @param type $scene 场景id 场景:0=p3d购买，1=f3d投注分配，2=f3d开奖分配'
     * @return boolean
     */
    private function sendP3d($user_id,$coin_id, $amount, $game_id, $scene){
        $tokenRecordM = new \addons\fomo\model\TokenRecord();
        $balanceM = new \addons\member\model\Balance();
        $rewardM = new \addons\fomo\model\RewardRecord();
        $total_amount = $tokenRecordM->getTotalToken(); //p3d总额
        $filter = '';
        if($scene == 0){
            //场景:0=p3d购买
            $filter = 'user_id !='.$user_id;
        }
        $user_list = $tokenRecordM->getDataList(-1,-1,$filter,'id,user_id,token','id asc');
        if(!empty($user_list)){
            foreach($user_list as $k => $user){
                if($user['token'] <= 0){
                    continue;
                }
                $user_id = $user['user_id'];
                $rate = $this->getUserRate($total_amount, $user['token']);
                $_amount = $amount * $rate; //所得分红
                //添加余额, 添加分红记录
                $balance = $balanceM->getBalanceByCoinID($user_id, $coin_id);
                $balance = $balanceM->updateBalance($user_id, $_amount, $coin_id, true);
                if($balance != false){
                    $before_amount = $balance['before_amount'];
                    $after_amount = $balance['amount'];
                    $type = 0; //奖励类型 0=投注分红，1=胜利战队分红，2=胜利者分红，3=邀请分红
                    $remark  = 'p3d投注分红';
                    $rewardM->addRecord($user_id, $coin_id, $before_amount, $_amount, $after_amount, $type, $game_id,$remark);
                }
            }
        }
        return true;
    }
    
    
    /**
     * 计算用户所拥有的key/token 数量占全部的百分比
     * @param type $total_amount
     * @param type $amount
     * @return type
     */
    private function getUserRate($total_amount, $amount){
        return $amount / $total_amount;
    }
    
}
