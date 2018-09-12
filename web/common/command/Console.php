<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/9/11
 * Time: 11:21
 */

namespace web\common\command;


use think\console\Command;
use think\console\Input;
use think\console\Output;

class Console extends Command
{
    protected function configure()
    {
        $this->setName('console')->setDescription('scheduled task');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->write('begin scheduled task:');
        $productM = new \web\mobile\controller\Product();
        $productM->teamIncome();
    }
}