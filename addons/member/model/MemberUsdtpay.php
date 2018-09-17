<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/9/12
 * Time: 19:38
 */

namespace addons\member\model;


class MemberUsdtpay extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'member_usdtpay';
    }
}