<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/9
 * Time: 17:20
 */

namespace addons\equity\model;


class PrivatePlacementRule extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'private_placement_rule';
    }

    public function getTotal($filter = '')
    {
        $sql = 'select count(*) c from ' . $this->getTableName() . ' where 1=1';
        if(!empty($filter))
            $sql .= ' and (' . $filter .')';
        $result = $this->query($sql);
        if(count($result) > 0)
            return intval($result[0]['c']);
        else
            return 0;
    }
}