<?php

namespace SevenDigital\Api;

use SevenDigital\Oauth\Interfaces\OauthInterface;
use SevenDigital\Oauth\OauthFactory;
use Curl\Curl;

class ApiClient{

    /**
     * @var array
     */
    private $config = array();

    /**
     * @var OauthInterface
     */
    private $oauth;

    private $curl;

    public function __construct(array $config){
        // TODO Validate config
        $this->config = $config;
        $oauthFactory = new OauthFactory();
        $this->oauth = $oauthFactory->create(1.0,$config);
        $this->curl = new Curl();
    }

    /**
     * @param array $params
     * @return bool
     */
    public function createUser(array $params){
        $requestUrl = $this->oauth->signRequest("GET","user/create",$params);

        $response = $this->curl->get($requestUrl);

        if($response->error){
            return false;
        } else{
            return $response->user;
        }
    }

    public function getStreamUrl(array $params){
        $oauthFactory = new OauthFactory();
        $oauth = $oauthFactory->create(1.0,$this->config);
        $requestUrl = $oauth->signRequest("GET","stream/subscription",$params);
        return $requestUrl;
    }

    /**
     * @param array $params
     * @return mixed
     * @throws MusicProviderException
     */
    public function subscribeUser(array $params){

        $date = new \DateTime("now",new \DateTimeZone("UTC"));
        $stringDate = $date->format("Y-m-d")."T".$date->format("H:m:s")."Z";
        $expireDate = $date->add(new \DateInterval("P30D"));
        $stringExpireDate = $date->format("Y-m-d")."T".$date->format("H:m:s")."Z";

        $params = $this->oauth->signRequest("POST","user/unlimitedStreaming",array_merge(array(
            "planCode" => "premium-unlimited-streaming",
            "status" => "active",
            "currency" => "USD",
            "recurringFee" => 0,
            "activatedAt" => $stringDate,
            "currentPeriodStartDate" => $stringDate,
            "expiryDate" => $stringExpireDate
        ),$params));

        $response = $this->curl->post($this->config["baseUrl"]."user/unlimitedStreaming",$params);
        if($response->streaming){
            return $response;
        } else{
            throw new MusicProviderException("Can't subscribe the user",MusicProviderException::INT_CREATE_ACCOUNT_ERROR);
        }
    }

    public function setBaseUrl($url){
        $this->config["baseUrl"] = $url;
    }


}