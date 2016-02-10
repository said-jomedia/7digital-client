<?php

namespace SevenDigital\Oauth;

use SevenDigital\Oauth\Interfaces\OauthInterface;

class Oauth implements OauthInterface
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function signRequest($method, $uri, array $params)
    {
        $requestBaseUrl = $this->config['baseUrl'].$uri;
        $consumerKey = $this->config['consumerKey'];
        $consumerSecret = $this->config['consumerSecret'];
        $oauthTimestamp = time();
        $nonce = md5(mt_rand());
        $oauthSignatureMethod = 'HMAC-SHA1';
        $oauthVersion = '1.0';

        $params = $params + array(
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce' => $nonce,
            'oauth_signature_method' => $oauthSignatureMethod,
            'oauth_timestamp' => $oauthTimestamp,
            'oauth_version' => $oauthVersion,
        );
        uksort($params, 'strcmp');

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $sigBase =  strtoupper($method).
            '&'.rawurlencode($requestBaseUrl).
            '&'.rawurlencode($query);

        $sigKey = rawurlencode($consumerSecret).'&';
        $oauthSig = base64_encode(hash_hmac('sha1', $sigBase, $sigKey, true));

        $requestUrl = $requestBaseUrl.'?'.$query.'&oauth_signature='.rawurlencode($oauthSig);

        if ('GET' == $method) {
            return $requestUrl;
        } elseif ('POST' == $method) {
            $params['oauth_signature'] = $oauthSig;
            return $params;
        }
    }
}
