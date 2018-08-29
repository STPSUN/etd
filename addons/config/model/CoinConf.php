<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/27
 * Time: 14:19
 */

namespace addons\config\model;


class CoinConf extends \web\common\model\BaseModel
{
    protected function _initialize()
    {
        $this->tableName = 'coin_conf';
    }

    public function getPriceByCoinId($coin_id)
    {
        $where['coin_id'] = $coin_id;
        $price = $this->where($where)->column('price');

        return empty($price) ? 0 : $price[0];
    }
}