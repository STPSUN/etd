<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/9/12
 * Time: 10:30
 */

namespace addons\member\model;


class MemberBuy extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'member_buy';
    }

    public function addRecord($userId,$amount,$releaseTime)
    {
        $data = array(
            'user_id'   => $userId,
            'amount'    => $amount,
            'release_time'  => $releaseTime,
            'create_time'   => NOW_DATETIME,
            'update_time'   => NOW_DATETIME,
            'status'    => 1,
        );

        return $this->save($data);
    }
}