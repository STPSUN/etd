<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/24
 * Time: 15:59
 */

namespace web\mobile\controller;


class Recognize extends Base
{
    public function index()
    {
        return $this->fetch();
    }
}