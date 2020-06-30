<?php

namespace HXC\Pay;

use HXC\App\Common;
use think\Config;
use think\Loader;
use think\Log;
use think\Request;

trait Pay
{
    use Common;

    private $ali_config;//支付宝配置
    private $wx_config;//微信配置
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
            $order = $this->getOrder('dev');
            $this->notify($order, 'dev');
        } else {//正式环境
            $func = $this->func[$params['type']][$params['func']];
            $config = ($params['type'] == 'wechat' ? $this->wx_config : $this->ali_config);
            $order = $this->getOrder($params['type']);
            $pay = \Yansongda\Pay\Pay::{$params['type']}($config)->{$func}($order);
            if ($params['type'] == 'wechat') {
                return $pay;
            } else {
                return $pay->send();
            }
        }
    }

    /**
     * 获取配置
     */
    public function getConfig()
    {
        $request = Request::instance();
        $domain = $request->domain();
        $config = Config::get('pay');
        $wx_pay_config = getSettings('wx_pay');
        $ali_pay_config = getSettings('ali_pay');
        $wx_config = $config['wx'];
        $ali_config = $config['ali'];
        $wx_config['notify_url'] = $domain . url('WXNotify');
        $ali_config['notify_url'] = $domain . url('ALiNotify');
        $this->wx_config = array_merge($wx_config, $wx_pay_config);
        $this->ali_config = array_merge($ali_config, $ali_pay_config);
        $this->env = $config['env'];
    }

    /**
     * 微信回调
     */
    public function WXNotify()
    {
        $pay = \Yansongda\Pay\Pay::wechat($this->wx_config);
        try {
            $data = $pay->verify();
            $this->notify($data->all(), 'wx');//处理回调
        } catch (\Exception $e) {
            Log::record('微信支付回调异常：' . $e->getMessage());
        }
        return $pay->success()->send();
    }

    /**
     * 支付宝回调
     */
    public function ALiNotify()
    {
        $pay = \Yansongda\Pay\Pay::alipay($this->ali_config);
        try {
            $data = $pay->verify();
            $this->notify($data->all(), 'ali');//处理回调
        } catch (\Exception $e) {
            Log::record('支付宝支付回调异常：' . $e->getMessage());
        }
        return $pay->success()->send();

    }
}