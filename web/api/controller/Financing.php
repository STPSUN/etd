<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace web\api\controller;

/**
 * Description of Financing
 * 理财
 *
 * @author shilinqing
 */
class Financing extends ApiBase{

    public function index(){
        $coin_id = $this->_get('coin_id');
        if(empty($coin_id)){
            return $this->failJSON('missing arguments');
        }
        try{
            $m = new \addons\financing\model\Product();
            $list = $m->getListByCoinID($coin_id);
            return $this->successJSON($list);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
        
    }
    
    public function loadCoins(){
        try{
            $m = new \addons\config\model\Coins();
            $data = $m->getCoinName();
            return $this->successJSON($data);
            
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
    public function getSafeConfig(){
        try{
            $m = new \web\common\model\sys\SysParameterModel();
            $where['field_name'] = array('in','is_safe,safe_Rate');
            $data = $m->where($where)->field('field_name,parameter_val,remark')->select();
            return $this->successJSON($data);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
    public function choiseProduct(){
        if(IS_POST){
            try{
                $user_id = $this->_post('user_id');
                $coin_id = $this->_post('coin_id');
                $plan_id = $this->_post('plan_id');
                $amount = floatval($this->_post('amount'));
                $is_safe = $this->_post('is_safe');
                if(empty($user_id) || empty($coin_id) || empty($plan_id) || empty($amount)){
                    return $this->failJSON('缺少参数');
                }
                $userM = new \addons\member\model\MemberAccountModel();
                $userAddr = $userM->getUserAddress($user_id);
                if(empty($userAddr)){
                    return $this->failJSON('用户不存在');
                }
                $safe_amount = 0;
                if($is_safe == 1){
                    $m = new \web\common\model\sys\SysParameterModel();
                    $where['field_name'] = 'safe_rate';
                    $sysConfig = $m->where($where)->find();
                    $rate = $sysConfig['parameter_val'];
                    $safe_amount = $amount * $rate / 100;
                }
                $total_amount = $amount + $safe_amount;
                //todo 判断余额是否足够
                $m = new \addons\member\model\AssetModel();
                $user_asset = $m->getUserCoin($user_id,$coin_id);
                if($user_asset['amount'] < $total_amount){
                    return $this->failJSON('余额不足');
                }
                $user_asset = $m->updateAsset($user_id, $total_amount, $coin_id);
                if(!$user_asset){
                    return $this->failJSON('更新余额失败');
                }
                $planM = new \addons\financing\model\Product();
                $plan = $planM->getDetail($plan_id);
                if(!empty($plan)){
                    $days = $plan['duration'];
                    $release_time = date('Y-m-d H:i:s',strtotime("+".$days." days"));
                }else{
                    return $this->failJSON('所选组合出错,请重新选择');
                }
                $pm = new \web\api\model\UserProduct();
                $data['user_id'] = $user_id;
                $data['coin_id'] = $coin_id;
                $data['plan_id'] = $plan_id;
                $data['amount'] = $amount;
                $data['is_safe'] = $is_safe;
                $data['safe_amount'] = $safe_amount;
                $data['total_amount'] = $total_amount;
                $data['release_time'] = $release_time;
                $data['add_time'] = NOW_DATETIME;
                $id = $pm->add($data);
                if($id > 0){
                    //添加记录
                    $recordM = new \addons\trade\model\AssetRecordModel();
                    $record_id = $recordM->addRecord($user_id,$coin_id,$total_amount,$user_asset,13,0,'','',"购买理财产品");
                    if($record_id > 0){
                        return $this->successJSON();
                    }
                }
           } catch (\Exception $ex) {
               return $this->failJSON($ex->getMessage());
           }
        }
        
    }
    
    public function getUserProductList(){
        $user_id = $this->_get('user_id');
        if(empty($user_id)){
            return $this->failJSON('missing arguments');
        }
        try{
            $m = new \web\api\model\UserProduct();
            $data = $m->getListByUserID($user_id);
            return $this->successJSON($data);
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
}
