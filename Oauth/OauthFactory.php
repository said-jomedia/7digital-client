<?php

namespace SevenDigital\Oauth;

use SevenDigital\Oauth\Interfaces\OauthInterface;

class OauthFactory{


    /**
     * @param $version
     * @param array $config
     * @return Oauth
     */
    public function create($version,array $config){
        switch($version){
            case 1.0:
                return new Oauth($config);
                break;
        }
    }

}