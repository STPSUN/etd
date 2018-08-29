<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace addons\fomo\index\controller;

/**
 * Description of FomoBase
 *
 * @author shilinqing
 */
class Fomobase extends \web\index\controller\AddonIndexBase{
    
    /**
     * 外网转入记录获取。
     * @return type
     */
    public function getEthOrders(){
        $coin_id = $this->_post('coin_id');
        $user_id = $this->user_id;
        $address = $this->address;
        if($user_id <= 0)
            return $this->failData('请登录');
        set_time_limit(200);
        $ethApi = new \EthApi();
        $coinM = new \addons\config\model\Coins();
        $coin = $coinM->getDetail($coin_id);
        if(!empty($coin)){
            $ethApi->set_byte($coin['byte']);
            if(!empty($coin['contract_address'])){
                $ethApi->set_contract($coin['contract_address']);
            }
            $transaction_list = $ethApi->erscan_order($address, $coin['is_token']);
            if(empty($transaction_list)){
                return $this->successData();
            }
            $res = $this->checkOrder($user_id, $address, $coin_id, $transaction_list);
        }
        return $this->successData();

    }
    
   /**
     * 外网数据写入
     * @param type $user_id 用户id
     * @param type $address 用户地址
     * @param type $list    抓取到的数据
     * @param type $coin_id 币种id
     * @return boolean
     */
    private function checkOrder($user_id, $address, $coin_id, $list){
        $m = new \addons\eth\model\EthTradingOrder();
        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();
        foreach($list as $val){
            $txhash = $val['hash'];
            $block_number = $val['block_number'];
            $from_address = $val['from'];
            try{
                $res = $m->getDetailByTxHash($txhash);//订单匹配
                if($res){
                    return true;
                }
                $m->startTrans();
                $amount = $val['amount'];
                $eth_order_id = $m->transactionIn($user_id, $from_address, $address, $coin_id, $amount, $txhash, $block_number, 0, 1, 1, "外网转入");
                if($eth_order_id > 0){
                    //插入转入eth记录成功
                    $balance = $balanceM->updateBalance($user_id, $amount, $coin_id, true);
                    if(!$balance){
                        $m->rollback();
                        return false;
                    }
                    $type = 2;
                    $before_amount = $balance['before_amount'];
                    $after_amount = $balance['amount'];
                    $change_type = 1; //增加
                    $remark = '外网转入';
                    $_id = $recordM->addRecord($user_id, $coin_id, $amount, $before_amount, $after_amount, $type, $change_type, $user_id, $address, '', $remark);
                    if(!$_id ){
                        $m->rollback();
                        return false;
                    }
                    $m->commit();
                    return true;
                }else{
                    $m->rollback();
                    return false;
                }
            } catch (\Exception $ex) {
                return false;
            }
        }
        return true;

    }
    
    protected function countRate($total_price, $rate){
        return $total_price * $rate / 100;
    }
    
    public function getBalance(){
        $coin_id = $this->_get('coin_id');
        $game_id = $this->_get('game_id');
        if($this->user_id <= 0){
            return $this->failData('未登录');
        }
        $rewardM = new \addons\fomo\model\RewardRecord();
        $data['invite_reward'] = $rewardM->getTotalByType($this->user_id, $coin_id);
        $data['other_reward'] = $rewardM->getTotalByType($this->user_id, $coin_id,'0,1,2');
        $balanceM = new \addons\member\model\Balance();
        $balance = $balanceM->getBalanceByCoinID($this->user_id, $coin_id);
        $data['balance'] = $balance['amount'];
        return $this->successData($data);
    }
    
    /**
     * 提取
     */
    public function withdraw(){
        if(IS_POST){
            if($this->user_id <= 0){
                return $this->failData('未登录');
            }
            $amount = $this->_post('amount');
            $coin_id = $this->_post('coin_id');
            $address = $this->_post('address');
            if( empty($coin_id) || empty($address) || empty($amount)){
                return $this->failData('缺少参数');
            }
            if($amount <= 0){
                return $this->failData('金额必须大于0');
            }
            $key_head = strtolower(substr($address,0,2));
            if(($key_head!=="0x" || strlen($address) !==42)){
                return $this->failData('address mast be started by 0x');
            }
            try{
                $balanceM = new \addons\member\model\Balance();
                $balance = $balanceM->getBalanceByCoinID($this->user_id, $coin_id);
                if(empty($balance)){
                    return $this->failData('余额不足');
                }
                $coinM = new \addons\config\model\Coins();
                $without_rate = $coinM->getCoinField($coin_id);
                $tax = 0;
                if(!empty($without_rate)){
                    $tax = $amount * $without_rate / 100;
                }
                $total_amount = $amount + $tax; //用户资产扣除总额
                if($balance['amount'] < $total_amount){
                    return $this->failData('余额不足');
                }
                $balanceM->startTrans();
                $before_amount = $balance['amount'];
                $balance['before_amount'] = $before_amount;
                $balance['amount'] = $before_amount - $total_amount;
                $balance['withdraw_frozen_amount'] = $balance['withdraw_frozen_amount'] + $total_amount;
                $balance['update_time'] = NOW_DATETIME;
                $ret = $balanceM->save($balance);
                if($ret > 0){
                    //保存提取订单
                    $ethM = new \addons\eth\model\EthTradingOrder();
                    $data['amount'] = $amount;
                    $data['tax'] = $tax;
                    $data['type'] = 0;//转出
                    $data['coin_id'] = $coin_id;
                    $data['to_address'] = $address;
                    $data['from_address'] = $this->address;
                    $data['user_id'] = $this->user_id;
                    $data['status'] = 0;
                    $data['update_time'] = NOW_DATETIME;
                    $id = $ethM->add($data);
                    if($id > 0){
                        $balanceM->commit();
                        return $this->successData();
                    }else{
                        $balanceM->rollback();
                        return $this->failData('提交提取失败');
                    }
                }else{
                    $balanceM->rollback();
                    return $this->failData('更新余额失败');
                }
            } catch (\Exception $ex) {
                $balanceM->rollback();
                return $this->failData($ex->getMessage());
            }
            
        }else{
            $this->assign('coin_id',$this->_get('coin_id'));
            $this->assign('id','0');
            $this->setLoadDataAction('');
            return $this->fetch('public/withdraw');
        }
    }
    

    
}
