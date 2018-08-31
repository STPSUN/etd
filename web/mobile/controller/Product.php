<?php
/**
 * Created by PhpStorm.
 * User: SUN
 * Date: 2018/8/31
 * Time: 10:55
 */

namespace web\mobile\controller;


use think\Request;
use think\Validate;

class Product extends Base
{
    private $ETD_ID = 13;
    public function index()
    {
        return $this->fetch();
    }

    public function getList()
    {
        $productM = new \addons\financing\model\Product();
        $list = $productM->getListByCoinID(13);

        for ($i = 0; $i < count($list); $i++)
        {
            $list[$i]['profit'] = bcdiv($list[$i]['rate'],$list[$i]['duration'],2);
        }

        return $this->successData($list);
    }

    public function toDetail()
    {
        return $this->fetch('detail');
    }

    public function detail()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'product_id'    => 'require',
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $product_id = $param['product_id'];
        $productM = new \addons\financing\model\Product();
        $data = $productM->getDetail($product_id);
        $data['profit'] = bcdiv($data['rate'],$data['duration'],2);

        return $this->successData($data);
    }

    public function buyProduct()
    {
        $param = Request::instance()->post();
        $validate = new Validate([
            'product_id'    => 'require',
            'amount|数量'        => 'require|integer'
        ]);
        if(!$validate->check($param))
            return $this->failData($validate->getError());

        $product_id = $param['product_id'];
        $amount = $param['amount'];

        $userM = new \addons\member\model\MemberAccountModel();
        $userAddr = $userM->getUserAddress($this->user_id);
        if(empty($userAddr))
            return $this->failData('用户钱包地址不存在');

        $balanceM = new \addons\member\model\Balance();
        $verify = $balanceM->verifyStock($this->user_id,$this->ETD_ID,$amount);
        if(!$verify)
            return $this->failData('余额不足');

        $balance = $balanceM->getBalanceByCoinID($this->user_id,$this->ETD_ID);

        $productM = new \addons\financing\model\Product();
        $verify_product = $productM->verifyStock($product_id,$amount);
        if(!$verify_product)
            return $this->failData('剩余额度不足');

        $product = $productM->getDetail($product_id);
        if(empty($product))
            return $this->failData('所选组合出错，请重新选择');
        $release_time = date('Y-m-d H:i:s',strtotime("+".$product['duration']." days"));

        $balanceM->startTrans();
        try
        {
            //更新余额
            $balanceM->updateBalance($this->user_id,$amount,$this->ETD_ID,false);

            //更新理财库存
            $productM->where('id',$product_id)->setDec('stock',$amount);

            //添加理财记录
            $data = array(
                'user_id'   => $this->user_id,
                'coin_id'   => $this->ETD_ID,
                'product_id'    => $product_id,
                'amount'    => $amount,
                'release_time'  => $release_time,
                'add_time'  => NOW_DATETIME,
            );
            $userProductM = new \web\api\model\UserProduct();
            $userProductM->save($data);

            //添加交易记录
            $recordM = new \addons\member\model\TradingRecord();
            $after_amount = $balance['amount'] - $amount;
            $recordM->addRecord($this->user_id,$this->ETD_ID,$amount,$balance['amount'],$after_amount,4,0,0);

            $balanceM->commit();
            return $this->successData();
        }catch (\Exception $e)
        {
            $balanceM->rollback();
            return $this->failData($e->getMessage());
        }
    }
}




















