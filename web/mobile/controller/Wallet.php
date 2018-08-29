<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/24
 * Time: 11:40
 */

namespace web\mobile\controller;


class Wallet extends Base
{
    public function index()
    {
        return $this->fetch();
    }

    public function toSetup()
    {
        return $this->fetch('setup');
    }
}














