<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/9/12
 * Time: 19:44
 */

namespace addons\member\user\controller;


class UsdtPay extends \web\user\controller\AddonUserBase
{
    private $ETD_ID = 13;
    private $USDT_ID = 1;
    public function index()
    {
        $status = $this->_get('status');
        if($status == ''){
            $status = 1; //未确认
        }

        $this->assign('status',$status);
        return $this->fetch();
    }

    public function loadList(){
        $keyword = $this->_get('keyword');
        $status = $this->_get('status');
        $filter = 'status='.$status;
        if ($keyword != null) {
            $filter .= ' and b.username like \'%' . $keyword . '%\'';
        }
        $m = new \addons\member\model\MemberUsdtpay();
        $total = $m->getTotal($filter);
        $rows = $m->getDataList($this->getPageIndex(), $this->getPageSize(), $filter);
        $data =  $this->toDataGrid($total, $rows);

        $userM = new \addons\member\model\MemberAccountModel();
        foreach ($data['rows'] as &$v)
        {
            $user = $userM->getDetail($v['user_id']);
            $v['username'] = $user['username'];
        }
        return $data;
    }

    public function appr()
    {
        if(IS_POST)
        {
            $id = $this->_post('id');
            $m = new \addons\member\model\MemberUsdtpay();
            $data = $m->getDetail($id);
            if(empty($data)){
                return $this->failData("数据异常");
            }

            $balanceM = new \addons\member\model\Balance();
            $balance = $balanceM->getBalanceByCoinID($data['user_id'],$this->ETD_ID);
            $m->startTrans();
            try
            {
                //更新充值表状态：已打款
                $m->save([
                    'status' => 2,
                    'update_time' => NOW_DATETIME,
                ],[
                    'id' => $id,
                ]);

                //更新用户余额
                $balanceM->updateBalance($data['user_id'],$data['amount'],$this->ETD_ID,true);
                //添加交易记录
                $recordM = new \addons\member\model\TradingRecord();
                $after = $balance['amount'] + $data['amount'];
                $recordM->addRecord($data['user_id'],$this->ETD_ID,$data['amount'],$balance['amount'],$after,14,1,$data['user_id'],'','','USDT充值');

                $m->commit();
                return $this->successData('id:'.$id.' 成功。');
            }catch (\Exception $e)
            {
                $m->rollback();
                return $this->failData("操作失败");
            }
        }
    }


    /**
     * 反审核-不通过
     */
    public function cancel_appr(){
        if(IS_POST){
            $id = $this->_post('id');

            $m = new \addons\member\model\MemberUsdtpay();
            $data = $m->getDetail($id);
            if(empty($data))
                $this->failData('数据异常');

            try{
                //更新充值状态：不通过
                $m->save([
                    'status' => 3,
                    'update_time' => NOW_DATETIME,
                ],[
                    'id' => $id,
                ]);
                return $this->successData('id:'.$id.' 成功。');
            } catch (\Exception $ex) {
                return $this->failData($ex->getMessage());
            }
        }
    }
}














