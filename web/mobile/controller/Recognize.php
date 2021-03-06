<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/24
 * Time: 15:59
 */

namespace web\mobile\controller;


use think\console\Output;
use think\Log;
use think\Request;
use think\Validate;

class Recognize extends Base
{
    private $placementRuleM;
    private $userM;
    private $balanceM;
    private $trandRecordM;
    private $ETD = 13;
    private $USDT = 1;
    private $memberBuyM;

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->placementRuleM = new \addons\equity\model\PrivatePlacementRule();
        $this->userM = new \addons\member\model\MemberAccountModel();
        $this->balanceM = new \addons\member\model\Balance();
        $this->trandRecordM = new \addons\member\model\TradingRecord();
        $this->memberBuyM = new \addons\member\model\MemberBuy();
    }

    public function index()
    {
        $rule = $this->placementRuleM->find();
        $user = $this->balanceM->where(['user_id' => $this->user_id, 'coin_id' => $this->ETD])->field('amount')->find();

        $data = array(
            'price' => $rule['price'],
            'surplus'   => $rule['gross'],
            'etd_num'   => empty($user['amount']) ? 0 : $user['amount'],
        );

        $this->assign('data',$data);
        return $this->fetch();
    }

    /**
     * 认筹
     */
    public function refer()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'num'   => 'require|number|>=:1000'
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $num = $param['num'];

        $rule = $this->placementRuleM->find();
        if($rule['gross'] < $num)
            return $this->failData('剩余认购数量不足');

        $total_price = $rule['price'] * $num;
        $verifyStock = $this->balanceM->verifyStock($this->user_id,$this->USDT,$total_price);
        if(!$verifyStock)
            return $this->failData('USDT余额不足');

        $balanceETD = $this->balanceM->where(['user_id' => $this->user_id, 'coin_id' => $this->ETD])->find();

        $this->placementRuleM->startTrans();
        try
        {
            //认筹数量更新
            $this->placementRuleM->where('id',1)->setDec('gross',$num);

            //用户余额更新
//            $this->balanceM->updateBalance($this->user_id,$num,$this->ETD,true);
            $this->balanceM->updateBalance($this->user_id,$total_price,$this->USDT,false);
            $this->balanceM->updateBuyAmount($this->user_id,$num,true);

            //添加认购记录
            $release_time = time() + 45*24*60*60;
            $this->memberBuyM->addRecord($this->user_id,$num,$release_time);

            //添加交易记录
            $after_amount = $balanceETD['buy_amount'] + $num;
            $this->trandRecordM->addRecord(0,$this->ETD,$num,$balanceETD['buy_amount'],$after_amount,13,1,$this->user_id,'','','认购');

            $this->placementRuleM->commit();
            return $this->successData();
        }catch (\Exception $e)
        {
            $this->placementRuleM->rollback();
        }
    }

    /**
     * 认筹列表
     */
    public function referList()
    {
        $where = array(
            'change_type' => 1,
            'type'  => 13,
        );
        $list = $this->trandRecordM->where($where)->field('to_user_id,amount,update_time')->select();

        return $this->successData($list);
    }

    /**
     * 释放认购的ETD
     */
    public function releaseBuy()
    {
        $where['release_time'] = array('<=',time());
        $where['status'] = 1;
        $data = $this->memberBuyM->where($where)->select();

        $this->balanceM->startTrans();
        try
        {
            foreach ($data as $v)
            {
                //用户余额更新
                $amount = bcmul($v['amount'],1.5,8);
                $this->balanceM->updateBalance($v['user_id'],$amount,$this->ETD,true);
                $this->balanceM->updateBuyAmount($v['user_id'],$amount,false);

                //更改认购状态
                $this->memberBuyM->save([
                    'status' => 2,
                    'update_time'   => NOW_DATETIME,
                ],[
                    'id' => $v['id']
                ]);

                //添加交易记录
                $balanceETD = $this->balanceM->where(['user_id' => $v['user_id'], 'coin_id' => $this->ETD])->find();
                $after_amount = $balanceETD['amount'] + $amount;
                $this->trandRecordM->addRecord(0,$this->ETD,$amount,$balanceETD['amount'],$after_amount,8,1,$v['user_id'],'','','认购冻结释放');
            }

            $this->balanceM->commit();
        }catch (\Exception $e)
        {
            $this->balanceM->rollback();
            return $this->failData($e->getMessage());
        }
    }

    /**
     * 认购15天，持币生息20%
     */
    public function firstAccrual()
    {
        $time = NOW_DATETIME;
        $where['create_time'] = array('<=',date('Y-m-d H:i:s',strtotime("$time - 15 day")));
        $where['status'] = 1;
        $where['accrual_status'] = 1;

        $data = $this->memberBuyM->where($where)->select();
        if(empty($data))
            return;

        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();

        $balanceM->startTrans();
        try
        {
            foreach ($data as $v)
            {
                //更改生息阶段状态
                $this->memberBuyM->save([
                    'accrual_status' => 2,
                    'update_time'   => NOW_DATETIME,
                ],[
                    'id' => $v['id']
                ]);

                $balance = $balanceM->getBalanceByCoinID($v['user_id'],$this->ETD);
                //更新冻结ETD金额
                $amount = bcmul($v['amount'],0.2,8);
                $balanceM->updateBuyAmount($v['user_id'],$amount,true);

                //添加流水记录
                $after_amount = $balance['buy_amount'] + $amount;
                $recordM->addRecord(0,$this->ETD,$amount,$balance['buy_amount'],$after_amount,15,1,$v['user_id'],'','','持币生息');
            }

            $balanceM->commit();
        }catch (\Exception $e)
        {
            $balanceM->rollback();
            return $this->failData($e->getMessage());
        }
    }

    /**
     * 认购45天，持币生息30%
     */
    public function secondAccrual()
    {
        $where['release_time'] = array('<=',time());
        $where['status'] = 1;
        $where['accrual_status'] = 2;

        $data = $this->memberBuyM->where($where)->select();
        if(empty($data))
            return;

        $balanceM = new \addons\member\model\Balance();
        $recordM = new \addons\member\model\TradingRecord();

        $balanceM->startTrans();
        try
        {
            foreach ($data as $v)
            {
                //更改生息阶段状态
                $this->memberBuyM->save([
                    'accrual_status' => 3,
                    'update_time'   => NOW_DATETIME,
                ],[
                    'id' => $v['id']
                ]);

                $balance = $balanceM->getBalanceByCoinID($v['user_id'],$this->ETD);
                //更新冻结ETD金额
                $amount = bcmul($v['amount'],0.3,8);
                $balanceM->updateBuyAmount($v['user_id'],$amount,true);

                //添加流水记录
                $after_amount = $balance['buy_amount'] + $amount;
                $recordM->addRecord(0,$this->ETD,$amount,$balance['buy_amount'],$after_amount,15,1,$v['user_id'],'','','持币生息');
            }

            $balanceM->commit();
        }catch (\Exception $e)
        {
            $balanceM->rollback();
            return $this->failData($e->getMessage());
        }
    }

    public function updateETD()
    {
        $data = $this->memberBuyM->where('status',1)->select();
        $m = new \addons\member\model\Balance();
        $m->startTrans();
        try
        {
            foreach ($data as $v)
            {
                //更新余额
                $m->updateBuyAmount($v['user_id'],$v['amount'],true);
            }

            $m->commit();
        }catch (\Exception $e)
        {
            $m->rollback();
            return $this->failData($e->getMessage());
        }
    }
}














