<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/9/10
 * Time: 14:44
 */

namespace addons\member\model;


class MemberTeam extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'member_team';
    }
}