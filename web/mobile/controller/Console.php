<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/10/17
 * Time: 17:00
 */

namespace web\mobile\controller;


use think\Log;

class Console extends Base
{
    public function etdtask()
    {
        $productM = new \web\mobile\controller\Product();
//        $productM->teamIncome();    //团队理财收益
//        $productM->income();        //个人理财收益
//        $productM->repeatBuyProduct();  //理财复投
        $recognizeM = new \web\mobile\controller\Recognize();
        $recognizeM->firstAccrual();    //持币生息，15天
        $recognizeM->secondAccrual();   //持币生息，45天
        $recognizeM->releaseBuy();  //认购冻结释放
    }
}