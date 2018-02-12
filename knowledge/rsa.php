<?php

header("Content-type: text/html; charset=utf-8");

/**
 * Class Rsa
 * 需要开启OpenSSL扩展
 * php Rsa非对称加密算法：公钥加密 => 私钥解密  |   私钥加密 => 公钥解密
 */
class Rsa
{
    public $privateKey = '';  //RSA私钥
    public $publicKey = '';   //RSA公钥

    /**
     * Rsa constructor.
     */
    public function __construct()
    {
        $resource = openssl_pkey_new(); //获取ssl资源句柄
        openssl_pkey_export($resource, $this->privateKey); //生成ssl私钥
        $detail = openssl_pkey_get_details($resource); //根据操作的句柄，获取对应的公钥
        $this->publicKey = $detail['key'];//ssl公钥
    }

    /**
     * @tip   RSA公钥加密
     * @param $data
     * @param $publicKey
     * @return mixed
     */
    public function publicEncrypt($data, $publicKey)
    {
        /**
         * @param string $data        需要加密的字符串
         * @param string $encrypted
         * @param mixed $key
         * @param int $padding [optional]  加密的方式
         * @return bool true on success or false on failure.
         */
        openssl_public_encrypt($data, $encrypted, $publicKey);
        return $encrypted;
    }

    /**
     * @param $data
     * @param $publicKey
     * @return mixed
     */
    public function publicDecrypt($data, $publicKey)
    {
        openssl_public_decrypt($data, $decrypted, $publicKey);
        return $decrypted;
    }

    /**
     * @param $data
     * @param $privateKey
     * @return mixed
     */
    public function privateEncrypt($data, $privateKey)
    {
        openssl_private_encrypt($data, $encrypted, $privateKey);
        return $encrypted;
    }

    /**
     * @param $data
     * @param $privateKey
     * @return mixed
     */
    public function privateDecrypt($data, $privateKey)
    {
        openssl_private_decrypt($data, $decrypted, $privateKey);
        return $decrypted;
    }
}


$rsa = new Rsa();
echo "公钥：\n", $rsa->publicKey, "<br /><br />";
echo "私钥：\n", $rsa->privateKey, "<br /><br />";

// 使用公钥加密
$str = $rsa->publicEncrypt('hello', $rsa->publicKey);
// 这里使用base64是为了不出现乱码，默认加密出来的值有乱码
$str = base64_encode($str);
echo "公钥加密（base64处理过）：\n", $str, "<br /><br />";
$str = base64_decode($str);
$privateStr = $rsa->privateDecrypt($str, $rsa->privateKey);
echo "私钥解密：\n", $privateStr, "<br /><br />";

// 使用私钥加密
$str = $rsa->privateEncrypt('world', $rsa->privateKey);
// 这里使用base64是为了不出现乱码，默认加密出来的值有乱码
$str = base64_encode($str);
echo "私钥加密（base64处理过）：\n", $str, "<br /><br />";
$str = base64_decode($str);
$pubstr = $rsa->publicDecrypt($str, $rsa->publicKey);
echo "公钥解密：\n", $pubstr, "<br /><br />";


echo "<hr />";
var_dump(openssl_pkey_get_private($rsa->privateKey));
var_dump(openssl_get_publickey($rsa->publicKey));