<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace web\api\controller;

/**
 * Description of Transfer
 *
 * @author shilinqing
 */
class Transfer extends ApiBase{
    //put your code here
    
    /**
     * 获取交易记录
     */
    public function getRecordList(){
        $user_id = $this->_get('user_id');
        $coin_id = $this->_get('coin_id');
        $type = $this->_get('type'); // 12 = 转出,11= 转入
        if(empty($user_id) || $type =='' || empty($coin_id)){
            return $this->failJSON('missing arguments');
        }
        try{
            $filter = 'user_id='.$user_id .' and coin_id='.$coin_id;
            if($type != 0){
                $filter .= ' and type='.$type;
            }else{
                $filter .= ' and (type=11 or type=12)';
            }
            $m = new \addons\trade\model\AssetRecordModel();

            $list = $m->getDataList($this->getPageIndex(),$this->getPageSize(),$filter);
            return $this->successJSON($list);
            
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
        
    }
    
    
    public function getUserCoinAsset(){
        $user_id = intval($this->_get('user_id'));
        $coin_id = intval($this->_get('coin_id'));
        if(empty($user_id)|| empty($coin_id)){
            return $this->failJSON('missing arguments');
        }
        $m = new \addons\member\model\AssetModel();
        $data = $m->getUserAsset($user_id,$coin_id);
        return $this->successJSON($data);
    }
    
    public function doTransfer(){
        if(IS_POST){
            $user_id = intval($this->_post('user_id'));
            $coin_id = intval($this->_post('coin_id'));
            $amount = floatval($this->_post('amount'));
            $to_address = $this->_post("to_address");
            if(!$amount || $amount <= 0){
                return $this->failJSON('请输入有效转账数量');
            }
            $m = new \addons\member\model\MemberAccountModel();
            $user_addr = $m->getUserAddress($user_id);
            if($to_address == $user_addr){
                return $this->failJSON('请勿输入自身钱包地址');
            }
            $key_head = strtolower(substr($to_address,0,2));
            if(($key_head!=="0x" || strlen($to_address) !==42)){
                return $this->failJSON('地址是由0X开头的42位16进制数组成');
            }
            $target_id = $m->getUserByAddress($to_address);
            if(empty($target_id)){
                return $this->doTransferOut($user_id,$coin_id,$amount,$to_address,$user_addr);
            }else{
                //转内网
                return $this->doTransferIn($user_id,$coin_id,$amount,$to_address,$user_addr,$target_id);
            }
            
        }
    }
    
    /**
     * 转内网
     * @param type $user_id
     * @param type $coin_id
     * @param type $amount
     * @param type $to_address
     * @param type $user_addr
     * @param type $target_id
     * @return type
     */
    private function doTransferIn($user_id,$coin_id,$amount,$to_address,$user_addr,$target_id){
        try{
            $AssetModel = new \addons\member\model\AssetModel();
            $userAsset = $AssetModel->getUserCoin($user_id,$coin_id);
            $AssetModel->startTrans();
            if($amount >= $userAsset['amount']){
                $AssetModel->rollback();
                return $this->failJSON('账户余额不足');
            }
            $userAsset = $AssetModel->updateAsset($user_id,$amount,$coin_id);
            if(!$userAsset){
                $AssetModel->rollback();
                return $this->failJSON('账号扣款失败');
            }
            $recordM = new \addons\trade\model\AssetRecordModel();
            $record_id = $recordM->addRecord($user_id,$coin_id,$amount,$userAsset,12,0,$to_address,$user_addr,"转出可用余额");
            if($record_id > 0){
                //用户转入
                $targetAsset = $AssetModel->updateAsset($target_id, $amount, $coin_id, true);
                if(!$userAsset){
                    $AssetModel->rollback();
                    return $this->failJSON('转账失败');
                }
                $in_record_id = $recordM->addRecord($target_id,$coin_id,$amount,$targetAsset,11,1,$to_address,$user_addr,"外网转入可用余额");
                if($in_record_id > 0){
                    $AssetModel->commit();
                    return $this->successJSON();
                }
            }
        } catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }


    private function doTransferOut($user_id,$coin_id,$amount,$to_address,$user_addr){
        try{
            $AssetModel = new \addons\member\model\AssetModel();
            $userAsset = $AssetModel->getUserCoin($user_id,$coin_id);
            $AssetModel->startTrans();
            if($amount >= $userAsset['amount']){
                $AssetModel->rollback();
                return $this->failJSON('账户余额不足');
            }
            $userAsset = $AssetModel->updateAsset($user_id,$amount,$coin_id);
            if(!$userAsset){
                $AssetModel->rollback();
                return $this->failJSON('账号扣款失败');
            }
            $tradeM = new \addons\trade\model\TradeModel();
            $trade_res = $tradeM->addTrade($user_id,$amount,0,0,$user_addr,$to_address,'',$coin_id,3,NOW_DATETIME);
            if(!$trade_res){
                $AssetModel->rollback();
                return $this->failJSON('提交申请失败');
            }
            $recordM = new \addons\trade\model\AssetRecordModel();
            $record_id = $recordM->addRecord($user_id,$coin_id,$amount,$userAsset,12,0,$to_address,$user_addr,"转出可用余额");
            if($record_id > 0){
                $AssetModel->commit();
                return $this->successJSON();
            }
        }catch (\Exception $ex) {
            return $this->failJSON($ex->getMessage());
        }
    }
    
}
