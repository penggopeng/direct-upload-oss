<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DirectUploadOssController extends Controller
{
    /**
     * @Notes: 获取 iso8601 日期
     * @Author: wp
     * @Date: 2023/8/30
     * @param $time
     * @return array|string|string[]
     */
    protected function gmt_iso8601($time)
    {
        return str_replace('+00:00', '.000Z', gmdate('c', $time));
    }

    /**
     * 配置信息
     */
    public function config()
    {
        $id  = 'LTAI4FcGnFStUb8dy3LJ88g1';          // 请填写您的AccessKeyId。
        $key = 'MnraKtsb7ZcA3qgcBP7kKE0kZgngQW';     // 请填写您的AccessKeySecret。

        // $host的格式为 bucketname.endpoint，请替换为您的真实信息。
        $host = 'http://w-poster.oss-cn-beijing.aliyuncs.com';

        // $callbackUrl为上传回调服务器的URL，请将下面的IP和Port配置为您自己的真实URL信息。
        //$callbackUrl = 'http://88.88.88.88:8888/aliyun-oss-appserver-php/php/callback.php';
        $callbackUrl = route('api.direct.upload.oss.callback');

        // 用户上传文件时指定的前缀。
        $dir = 'direct-upload-oss/';

        $callback_param       = array(
            'callbackUrl'      => $callbackUrl,
            'callbackBody'     => 'filename=${object}&size=${size}&mimeType=${mimeType}&height=${imageInfo.height}&width=${imageInfo.width}',
            'callbackBodyType' => "application/x-www-form-urlencoded"
        );
        $callback_string      = json_encode($callback_param);
        $base64_callback_body = base64_encode($callback_string);
        $now                  = time();
        $expire               = 30;  //设置该policy超时时间是10s. 即这个policy过了这个有效时间，将不能访问。
        $end                  = $now + $expire;
        $expiration           = $this->gmt_iso8601($end);


        //最大文件大小.用户可以自己设置
        $condition    = array(0 => 'content-length-range', 1 => 0, 2 => 1048576000);
        $conditions[] = $condition;

        // 表示用户上传的数据，必须是以$dir开始，不然上传会失败，这一步不是必须项，只是为了安全起见，防止用户通过policy上传到别人的目录。
        $start        = array(0 => 'starts-with', 1 => '$key', 2 => $dir);
        $conditions[] = $start;

        $arr            = array('expiration' => $expiration, 'conditions' => $conditions);
        $policy         = json_encode($arr);
        $base64_policy  = base64_encode($policy);
        $string_to_sign = $base64_policy;
        $signature      = base64_encode(hash_hmac('sha1', $string_to_sign, $key, true));

        $response              = array();
        $response['accessid']  = $id;
        $response['host']      = $host;
        $response['policy']    = $base64_policy;
        $response['signature'] = $signature;
        $response['expire']    = $end;
        $response['callback']  = $base64_callback_body;
        $response['dir']       = $dir;  // 这个参数是设置用户上传文件时指定的前缀。
        $this->ajaxReturn($response);
    }

    /**
     * 回调信息
     */
    public function callback(Request $request)
    {
        info('回调所有信息' . json_encode($request->all()));

        // 1.获取OSS的签名header和公钥url header
        $authorizationBase64 = "";
        $pubKeyUrlBase64     = "";

        /*
         * 注意：如果要使用HTTP_AUTHORIZATION头，你需要先在apache或者nginx中设置rewrite，以apache为例，修改
         * 配置文件/etc/httpd/conf/httpd.conf(以你的apache安装路径为准)，在DirectoryIndex index.php这行下面增加以下两行
            RewriteEngine On
            RewriteRule .* - [env=HTTP_AUTHORIZATION:%{HTTP:Authorization},last]
         * */
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorizationBase64 = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (isset($_SERVER['HTTP_X_OSS_PUB_KEY_URL'])) {
            $pubKeyUrlBase64 = $_SERVER['HTTP_X_OSS_PUB_KEY_URL'];
        }

        if ($authorizationBase64 == '' || $pubKeyUrlBase64 == '') {
            header("http/1.1 403 Forbidden");
            exit();
        }

        // 2.获取OSS的签名
        $authorization = base64_decode($authorizationBase64);

        // 3.获取公钥
        $pubKeyUrl = base64_decode($pubKeyUrlBase64);
        $ch        = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pubKeyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $pubKey = curl_exec($ch);
        if ($pubKey == "") {
            exit();
        }

        // 4.获取回调body
        $body = file_get_contents('php://input');

        // 5.拼接待签名字符串
        $authStr = '';
        $path    = $_SERVER['REQUEST_URI'];
        $pos     = strpos($path, '?');
        if ($pos === false) {
            $authStr = urldecode($path) . "\n" . $body;
        } else {
            $authStr = urldecode(substr($path, 0, $pos)) . substr($path, $pos, strlen($path) - $pos) . "\n" . $body;
        }

        // 6.验证签名
        $ok = openssl_verify($authStr, $authorization, $pubKey, OPENSSL_ALGO_MD5);
        if ($ok == 1) {
            header("Content-Type: application/json");
            $data = array("Status" => "Ok");
            echo json_encode($data);
        } else {
            exit();
        }

    }


}
