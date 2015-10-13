<?php

namespace SevenDigital\Oauth\Interfaces;

interface OauthInterface{


    public function __construct(array $config);

    /**
     * @param String $method
     * @param String $url
     * @param array $params
     * @return mixed
     */
    public function signRequest($method, $url, array $params);

}