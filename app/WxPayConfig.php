<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018-09-12
 * Time: 18:59
 */

namespace App;


class WxPayConfig extends \WxPayConfigInterface
{
    public function GetAppId()
    {
        return 'wx6d72e8d8e41665e3';
    }
    public function GetMerchantId()
    {
        return '1509797861';
    }

    public function GetNotifyUrl()
    {
        return "http://testapi.adbug.cn/wx/notify";
    }
    public function GetSignType()
    {
        return "HMAC-SHA256";
    }

    public function GetProxy(&$proxyHost, &$proxyPort)
    {
        $proxyHost = "0.0.0.0";
        $proxyPort = 0;
    }


    public function GetReportLevenl()
    {
        return 1;
    }


    //=======【商户密钥信息-需要业务方继承】===================================
    /*
     * KEY：商户支付密钥，参考开户邮件设置（必须配置，登录商户平台自行设置）, 请妥善保管， 避免密钥泄露
     * 设置地址：https://pay.weixin.qq.com/index.php/account/api_cert
     *
     * APPSECRET：公众帐号secert（仅JSAPI支付的时候需要配置， 登录公众平台，进入开发者中心可设置）， 请妥善保管， 避免密钥泄露
     * 获取地址：https://mp.weixin.qq.com/advanced/advanced?action=dev&t=advanced/dev&token=2005451881&lang=zh_CN
     * @var string
     */
    public function GetKey()
    {
        return 'IpCtDgM6IA9ZyZBHPyiJxnEHwElKnmji';
    }
    public function GetAppSecret()
    {
        return 'fc54a9d1148f3b21f7f0444a3c805489';
    }


    //=======【证书路径设置-需要业务方继承】=====================================
    /**
     * TODO：设置商户证书路径
     * 证书路径,注意应该填写绝对路径（仅退款、撤销订单时需要，可登录商户平台下载，
     * API证书下载地址：https://pay.weixin.qq.com/index.php/account/api_cert，下载之前需要安装商户操作证书）
     * 注意:
     * 1.证书文件不能放在web服务器虚拟目录，应放在有访问权限控制的目录中，防止被他人下载；
     * 2.建议将证书文件名改为复杂且不容易猜测的文件名；
     * 3.商户服务器要做好病毒和木马防护工作，不被非法侵入者窃取证书文件。
     * @var path
     */
    public function GetSSLCertPath(&$sslCertPath, &$sslKeyPath)
    {
        $sslCertPath = '../cert/apiclient_cert.pem';
        $sslKeyPath = '../cert/apiclient_key.pem';
    }
}

