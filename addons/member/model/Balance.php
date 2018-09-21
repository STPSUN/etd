<?php

namespace addons\member\model;

/**
 * 用户资产
 *
 * @author shilinqing
 */
class Balance extends \web\common\model\BaseModel
{
    private $ETD_ID = 13;

    protected function _initialize()
    {
        $this->tableName = 'member_balance';
    }

    /**
     * get user balance list
     * @param type $user_id
     * @param type $coin_id
     * @return type
     */
    public function getUserBalanceList($user_id, $coin_id = '')
    {
        $m = new \addons\config\model\Coins();
        $marketM = new \web\api\model\MarketModel();
        $sql = 'select a.id,a.amount,a.coin_id,b.coin_name,b.pic,(c.cny * a.amount) as cny from ' . $this->getTableName() . ' a,' . $m->getTableName() . ' b,' . $marketM->getTableName() . ' c where a.user_id=' . $user_id . ' and a.coin_id=b.id and b.coin_name=c.coin_name';
        if (!empty($coin_id)) {
            $sql .= ' and a.coin_id=' . $coin_id;
        }
        return $this->query($sql);
    }

    public function verifyStock($user_id,$coid_id,$num)
    {
        $where['user_id'] = $user_id;
        $where['coin_id'] = $coid_id;

        $amount = $this->where($where)->column('amount');
        if(empty($amount))
            return false;

        if($amount[0] >= $num)
        {
            return true;
        }else
        {
            return false;
        }
    }

    /**
     * get user balance by coin id , if null then add new data
     * @param type $user_id
     * @param type $coin_id
     * @return int
     */
    public function getBalanceByCoinID($user_id, $coin_id)
    {
        $where['user_id'] = $user_id;
        $where['coin_id'] = $coin_id;
        return $this->where($where)->find();
    }

    /**
     * 更新用户资产
     * @param type $user_id
     * @param type $amount 变动金额
     * @param type $coin_id 变动币种
     * @param type $type 变动类型，false 减值，true增值
     * @return type
     */
    public function updateBalance($user_id, $amount, $coin_id, $type = false)
    {
        $map = array();
        $map['user_id'] = $user_id;
        $map['coin_id'] = $coin_id;
        $userAsset = $this->where($map)->find();
        if (!$userAsset) {
            $userAsset['user_id'] = $user_id;
            $userAsset['before_amount'] = 0;
            $userAsset['amount'] = 0;
            $userAsset['total_amount'] = 0;
            $userAsset['coin_id'] = $coin_id;
            $userAsset['buy_amount'] = 0;
        }
        $userAsset['update_time'] = NOW_DATETIME;
        if ($type) {
            $userAsset['before_amount'] = $userAsset['amount'];
            $userAsset['amount'] = $userAsset['amount'] + $amount;
            $userAsset['total_amount'] = $userAsset['total_amount'] + $amount;
        } else {
            $userAsset['before_amount'] = $userAsset['amount'];
            $userAsset['amount'] = $userAsset['amount'] - $amount;
            $userAsset['total_amount'] = $userAsset['total_amount'] - $amount;
        }
        $res = $this->save($userAsset);
        if (!$res) {
            return false;
        }
        return $userAsset;
    }

    /**
     * 更新冻结ETD
     * @param $user_id
     * @param $amount
     * @param $coin_id
     * @param bool $type
     * @return bool
     */
    public function updateBuyAmount($user_id,$amount,$type = false)
    {
        $map = array();
        $map['user_id'] = $user_id;
        $map['coin_id'] = $this->ETD_ID;
        $userAsset = $this->where($map)->find();
        if(!$userAsset)
        {
            $userAsset['user_id'] = $user_id;
            $userAsset['before_amount'] = 0;
            $userAsset['amount'] = 0;
            $userAsset['total_amount'] = 0;
            $userAsset['coin_id'] = $this->ETD_ID;
            $userAsset['buy_amount'] = 0;
        }
        $userAsset['update_time'] = NOW_DATETIME;
        if($type)
        {
            $userAsset['buy_amount'] = $userAsset['buy_amount'] + $amount;
        }else
        {
            $userAsset['buy_amount'] = $userAsset['buy_amount'] - $amount;
        }

        $res = $this->save($userAsset);
        if(!$res)
        {
            return false;
        }

        return $userAsset;
    }

}
