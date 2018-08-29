<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/29
 * Time: 13:45
 */

namespace web\mobile\controller;


class Upload extends Base
{
    public function upload()
    {
        $file = request()->file('image');
        $order_id = request()->param('order_id');

        if($file)
        {
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads/deal');
            if($info)
            {
                $savename = $info->getSaveName();
                $orderM = new \addons\otc\model\OtcOrder();
                $orderM->save([
                    'pic'   => $savename,
                    'status'    => 2,
                ],['id' => $order_id]);

                return $this->fetch('deal/index');
            }else
            {
                return $this->failData($file->getError());
            }
        }else
        {
            $this->assign('id',$order_id);
            return $this->fetch('deal/upload');
        }
    }
}