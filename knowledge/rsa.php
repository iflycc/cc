<?php

header("Content-type: application/json; charset=utf-8");

/**
 * Class Rsa
 * 需要开启OpenSSL扩展
 * php Rsa非对称加密算法：公钥加密 => 私钥解密  |   私钥加密 => 公钥解密
 *
 *
 * 格式要求：
 *  一. RSA PEM文件格式
 *
 *  1. PEM私钥格式文件
 *      -----BEGIN RSA PRIVATE KEY-----
 *      -----END RSA PRIVATE KEY-----
 *
 *  2. PEM公钥格式文件
 *      -----BEGIN PUBLIC KEY-----
 *      -----END PUBLIC KEY-----
 *
 *  3. PEM RSAPublicKey公钥格式文件
 *      -----BEGIN RSA PUBLIC KEY-----
 *      -----END RSA PUBLIC KEY-----
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
        $config =
            [
                "digest_alg"        => "sha512",
                "private_key_bits"     => 4096,           //字节数  512 1024 2048  4096 等
                "private_key_type"     => OPENSSL_KEYTYPE_RSA,   //加密类型
            ];
        $resource = openssl_pkey_new($config); //获取ssl资源句柄
        openssl_pkey_export($resource, $this->privateKey); //生成ssl私钥
        $detail = openssl_pkey_get_details($resource); //根据操作的句柄，获取对应的公钥
        $this->publicKey = $detail['key'];//ssl公钥
    }

    /**
     * @tip   RSA公钥加密
     * @param string $data 待加密的字符串
     * @param string $publicKey rsa公钥
     * @return mixed 【乱码，为避免网络传输被转义，需做base64_encode】
     */
    public function publicEncrypt($data, $publicKey)
    {
        openssl_public_encrypt($data, $encrypted, $publicKey);
        $encrypted = base64_encode($encrypted);
        return $encrypted;
    }

    /**
     * Rsa 公钥解密
     * @param string $data 待解密的字符串
     * @param string $publicKey rsa公钥
     * @return mixed 公钥解密后的字符串 【为了避免网络传输转义，在传输之前已经做过了base64_encode，故需做base64_decode】
     */
    public function publicDecrypt($data, $publicKey)
    {
        $data = base64_decode($data);
        openssl_public_decrypt($data, $decrypted, $publicKey);
        return $decrypted;
    }

    /**
     * Rsa私钥加密
     * @param string $data 待加密的字符串
     * @param string $privateKey rsa私钥
     * @return mixed 私钥加密后的字符串 【乱码，为避免网络传输被转义，需做base64_encode】
     */
    public function privateEncrypt($data, $privateKey)
    {
        openssl_private_encrypt($data, $encrypted, $privateKey);
        $encrypted = base64_encode($encrypted);
        return $encrypted;
    }

    /**
     * Rsa私钥解密
     * @param string $data 带解密的字符串
     * @param string $privateKey Rsa私钥
     * @return mixed 【为了避免网络传输转义，在传输之前已经做过了base64_encode，故需做base64_decode】
     */
    public function privateDecrypt($data, $privateKey)
    {
        $data = base64_decode($data);
        openssl_private_decrypt($data, $decrypted, $privateKey);
        return $decrypted;
    }

    #▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅Rsa加密及验证▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅

    /**
     * @param string $data 参与签名的源字符串
     * @param $rsaPrivateKey
     * @return string 生成的加密串 【注：该加密串不能解析，只能用 `源字符串` + `公钥`进行验证】
     */
    public function sign($data,$rsaPrivateKey)
    {
        $resourceId = openssl_get_privatekey($rsaPrivateKey); #这个函数可用来判断私钥是否是可用的，可用返回资源id Resource id
        empty($resourceId) && die("无效的私钥");
        openssl_sign ( $data ,  $sign ,  $resourceId ,  OPENSSL_ALGO_SHA512 );
        openssl_free_key($resourceId);
        return base64_encode($sign);
    }

    /**
     * @param string $data 参与rsa加密的源字符串
     * @param string $sign 私钥加密后的、待验证的字符串
     * @param string $rsaPublicKey rsa公钥
     * @return int 验证结果
     */
    public function verify($data,$sign,$rsaPublicKey)
    {
        $resourceId = openssl_get_publickey($rsaPublicKey); // 用于验证公钥是否可用，返回资源id，否则返回false
        empty($resourceId) && die("无效的公钥");
        $sign = base64_decode($sign);
        $res = openssl_verify($data,$sign,$rsaPublicKey,OPENSSL_ALGO_SHA512); # 验证通过返回1，否则返回0
        return $res;
    }

}


$rsa = new Rsa();
echo "公钥：\n", $rsa->publicKey, PHP_EOL;
echo "私钥：\n", $rsa->privateKey, PHP_EOL;

// 使用公钥加密
$str = $rsa->publicEncrypt('hello', $rsa->publicKey);
// 这里使用base64是为了不出现乱码，默认加密出来的值有乱码
echo "公钥加密（base64处理过）：\n", $str, PHP_EOL;
$privateStr = $rsa->privateDecrypt($str, $rsa->privateKey);
echo "私钥解密：\n", $privateStr, PHP_EOL;

// 使用私钥加密
$str = $rsa->privateEncrypt('world', $rsa->privateKey);
// 这里使用base64是为了不出现乱码，默认加密出来的值有乱码
echo "私钥加密（base64处理过）：\n", $str, PHP_EOL;
$pubstr = $rsa->publicDecrypt($str, $rsa->publicKey);
echo "公钥解密：\n", $pubstr, PHP_EOL;

# 验证
$demoStr = "JustDoIT.";
$sign = $rsa->sign($demoStr,$rsa->privateKey);
$verifyRs = $rsa->verify($demoStr,$sign,$rsa->publicKey);
echo "验证结果：",($verifyRs ? "成功！" : "失败！"),PHP_EOL;

echo PHP_EOL,PHP_EOL,PHP_EOL;
var_dump(openssl_pkey_get_private($rsa->privateKey));
var_dump(openssl_get_publickey($rsa->publicKey));
