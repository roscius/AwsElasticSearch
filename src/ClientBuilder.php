<?php
namespace Roscius\AwsElasticSearch;

use GuzzleHttp\Ring\Client\CurlHandler;
use GuzzleHttp\Ring\Client\CurlMultiHandler;
use GuzzleHttp\Ring\Client\Middleware;
use GuzzleHttp\Psr7\Request;
use Aws\Signature\SignatureV4;
use Aws\Credentials\Credentials;

class ClientBuilder extends \Elasticsearch\Clientbuilder
{
    /**
     * @param string $key AWS IAM User Key
     * @param string $secret AWS IAM User Secret
     * @param string $region AWS Region
     * @param array $multiParams Parameters to pass to CURL
     * @param array $singleParams Parmaters to pass to CURL
     *
     * @return ClientBuilder
     */
    public function setAwsHandler($key, $secret, $region = 'us-east-1', $multiParams = [], $singleParams = [])
    {
        $future = null;

        if (extension_loaded('curl')) {
            $config = array_merge(['mh' => curl_multi_init()], $multiParams);
            if (function_exists('curl_reset')) {
                $default = new CurlHandler($singleParams);
                $future = new CurlMultiHandler($config);
            } else {
                $default = new CurlMultiHandler($config);
            }
        } else {
            throw new \RuntimeException('Elasticsearch-PHP requires cURL, or a custom HTTP handler.');
        }

        $curlHandler = $future ? Middleware::wrapFuture($default, $future) : $default;

        $awsSignedHandler = function (array $request) use ($curlHandler, $region, $key, $secret) {

            $signer = new SignatureV4('es', $region);

            $credentials = new Credentials(
                $key,
                $secret
            );

            $psr7Request = new Request(
                $request['http_method'],
                $request['uri'],
                $request['headers'],
                $request['body']
            );

            $signedRequest = $signer->signRequest($psr7Request, $credentials);

            $request['headers'] = $signedRequest->getHeaders();

            return $curlHandler($request);
        };

        $this->setHandler($awsSignedHandler);
        return $this;
    }
}
