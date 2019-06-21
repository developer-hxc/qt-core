<?php
/**
 * Created by PhpStorm.
 * Auth: Administrator
 * Date: 2019-04-18
 * Time: 17:25
 */

namespace HXC\App;
use think\Route;

class routerFun
{
    private $apiVersion;
    private $router;

    /**
     * routerFun constructor.
     */
    public function __construct($apiVersion,$router)
    {
        $this->setApiVersion($apiVersion);
        $this->setRouter($router);
    }

    /**
     * @param mixed $apiVersion
     */
    public function setApiVersion($apiVersion)
    {
        $this->apiVersion = $apiVersion;
    }

    /**
     * @param mixed $router
     */
    public function setRouter($router)
    {
        $this->router = $router;
    }

    /**
     * @return mixed
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * @return mixed
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * 生成api路由
     */
    public function generate()
    {
        $apiVersion = $this->getApiVersion();
        $router = $this->getRouter();
        //api路由生成
        foreach ($apiVersion as $key => $val){
            Route::group('api',function()use($router,$val){
                Route::group($val,function()use($router,$val){
                    foreach ($router as $k => $v){
                        $methods = $v[0];
                        Route::$methods($k,is_object($v[1])?$v[1]:"app/{$val}.{$v[1]}");
                    }
                });
            });
        }
    }

    /**
     * 生成文档介绍
     */
    public function doc()
    {
        $router = $this->getRouter();
        $url = [];
        foreach ($router as $k => $v){
//            $data['url'] = $k
        }
//        Route::get('doc',)
    }
}