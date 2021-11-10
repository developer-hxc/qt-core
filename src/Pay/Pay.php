<?php

namespace HXC\Pay;

use HXC\App\Common;
use Psr\Http\Message\ResponseInterface;
use think\Config;
use think\Loader;
use think\Log;
use think\Request;
use Yansongda\Supports\Collection;

trait Pay
{
    use Common;

    private $config = [];
    private $env;//dev开发环境：不走微信/支付宝支付，直接支付成功；production线上环境：走微信/支付宝支付
    private $func = [
        //微信支付
        'wechat' => [
            'app' => 'app',//app支付
            'mp' => 'mp',//公众号支付
            'wap' => 'wap',//h5支付
            'mini' => 'miniapp',//小程序支付
        ],
        //支付宝支付
        'alipay' => [
            'app' => 'app', //app支付
            'mini' => 'mini', //小程序支付
            'wap' => 'wap', //手机网站支付
            'web' => 'web',//网页支付
        ]
    ];

    /**
     * 初始化
     */
    public function _initialize()
    {
        $this->getConfig();
    }

    /**
     * 支付
     * @param Request $request
     * @return \think\response\Json
     */
    public function pay(Request $request)
    {
        $params = $request->only(['type', 'func']);
        $validate = Loader::validate('Qtpay');
        if (!$validate->check($params)) {
            return $this->returnFail($validate->getError());
        }
        if ($this->env === 'dev') {//开发环境
            $order = $this->getOrder($params,'dev');
            $this->notify($order, 'dev');
        } else {//正式环境
            $func = $this->func[$params['type']][$params['func']];
            $order = $this->getOrder($params);
            \Yansongda\Pay\Pay::config($this->config);
            $pay = \Yansongda\Pay\Pay::{$params['type']}()->{$params['func']}($order);
            return $this->getResponseBody($pay);
        }
    }

    /**
     * 获取配置
     */
    public function getConfig()
    {
        $config = Config::get('pay');
        $this->config = $config;
        $this->env = $config['env'];
    }

    private function getResponseBody($pay)
    {
        if(is_array($pay)){
            return $pay;
        }

        if(!is_object($pay)){
            throw new \Exception('返回格式不正确');
        }

        if($pay instanceof \GuzzleHttp\Psr7\Response){
            return $pay->getBody()->getContents();
        }

        if($pay instanceof \Yansongda\Supports\Collection){
            return $pay->all();
        }

        return $pay;
    }

    /**
     * 微信回调
     */
    public function WXNotify()
    {
        try {
            \Yansongda\Pay\Pay::config($this->config);
            $result =  \Yansongda\Pay\Pay::wechat()->callback();
            $this->notify($result->all(), 'wx');//处理回调
        } catch (\Exception $e) {
            Log::record('微信支付回调异常：' . $e->getMessage());
        }
        return  \Yansongda\Pay\Pay::wechat()->success();
    }

    /**
     * 支付宝回调
     */
    public function ALiNotify()
    {
        try {
            \Yansongda\Pay\Pay::config($this->config);
            $result =  \Yansongda\Pay\Pay::alipay()->callback();
            $this->notify($result->all(), 'ali');//处理回调
        } catch (\Exception $e) {
            Log::record('支付宝支付回调异常：' . $e->getMessage());
        }
        return  \Yansongda\Pay\Pay::alipay()->success();

    }
}