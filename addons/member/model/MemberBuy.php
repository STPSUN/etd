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
}