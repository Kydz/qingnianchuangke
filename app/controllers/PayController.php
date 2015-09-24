<?php
/**
*
*/
class PayController extends \BaseController
{
    public function callbackAlipay()
    {
        $order_no = Input::get('out_trade_no', 0);
        $ali_trade_no = Input::get('trade_no', '');
        $ali_trade_status = Input::get('trade_status', '');
        $amount = Input::get('total_fee', 0);

        DB::beginTransaction();
        try {
            $alipay = new Alipay();
            $alipay->verifyNotify();

            $order = Order::getOrderByNo($order_no);

            if ($ali_trade_status == 'TRADE_FINISHED' || $ali_trade_status == 'TRADE_SUCCESS') {
                $order->pay($amount, Alipay::PAYMENT_TAG);
                $order->checkoutCarts();
            }
            DB::commit();
            echo "success";
        } catch (Exception $e) {
            DB::rollback();
            echo "fail";
        }
        exit;
    }

    public function callbackWechat()
    {
        $return_code = Input::get('return_code', 'FAIL');
        $return_msg = Input::get('return_msg', '没有获取到返回');

        $wechat = new WechatPay();

        DB::beginTransaction();
        try {
            if ($return_code == 'FAIL') {
                throw new Exception("微信服务器返回错误", 9001);
            }
            $re = $wechat->verifyNotify();
            $order = Order::getOrderByNo($re['out_trade_no']);
            $order->pay($re['total_fee'], WechatPay::PAYMENT_TAG);
            $order->checkoutCarts();
            $wechat->_notify->SetReturn_code('SUCCESS');
            $wechat->_notify->SetReturn_msg('OK');
            DB::commit();
        } catch (Exception $e) {
            $wechat->_notify->SetReturn_code('FAIL');
            $wechat->_notify->SetReturn_msg($e->getMessage());
            DB::rollback();
        }
        $re = $wechat->_notify->ToXml();
        WxpayApi::replyNotify($re);
        exit;
    }

    public function wechatPayPreOrder()
    {
        $order_no = Input::get('order_no', '');
        $token = Input::get('token', '');
        $u_id = Input::get('u_d', 0);

        try {
            $user = User::chkUserByToken($token, $u_id);
            $order = Order::getOrderByNo($order_no);
            $product_names = Cart::where('o_id', '=', $order->o_id)->lists('p_name');
            $wechat = new WechatPay();
            $body = $product_names[0].'等商品';
            $params = [
                'out_trade_no' => $order_no,
                'total_fee' => $order->o_amount,
                'body' => $body,
                'detail' => implode(',', $product_names)
            ];
            $re = $wechat->preOrder($params);
            $data = ['prepay_id' => $re['prepay_id'], 'sign' => $re['sign']];
            $re = Tools::reTrue('微信预支付成功', $data);
        } catch (Exception $e) {
            $re = Tools::reFalse($e->getCode(), '微信预支付失败:'.$e->getMessage());
        }
        return Response::json($re);
    }
}
