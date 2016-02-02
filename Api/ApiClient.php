<?php
/**
 *
 */

namespace SevenDigital\Api;

use JoMedia\Music\MusicProviderException;
use SevenDigital\Oauth\Interfaces\OauthInterface;
use SevenDigital\Oauth\OauthFactory;
use Curl\Curl;
use \DateTime;
use \DateInterval;
use \DateTimeZone;
use \Exception;

/**
 * ApiClient 
 */
class ApiClient
{
    /**
     * @var array
     */
    private $config = array();

    /**
     * @var OauthInterface
     */
    private $oauth;

    /**
     * curl 
     * @var Curl object
     */
    private $curl;

    /**
     * __construct 
     * 
     * @param array $config config 
     * 
     * @return void
     */
    public function __construct(array $config)
    {
        // TODO Validate config
        $this->config = $config;
        $oauthFactory = new OauthFactory();
        $this->oauth = $oauthFactory->create(1.0, $config);
        $this->curl = new Curl();
    }

    /**
     * @param array $params
     * @return bool
     */
    public function createUser(array $params)
    {
        $requestUrl = $this->oauth->signRequest('GET', 'user/create', $params);

        $response = $this->curl->get($requestUrl);

        if ($response->error) {
            return false;
        } else {
            return $response->user;
        }
    }

    /**
     * getStreamUrl 
     * 
     * @param array $params params 
     * 
     * @return void
     */
    public function getStreamUrl(array $params)
    {
        $oauthFactory = new OauthFactory();
        $oauth = $oauthFactory->create(1.0, $this->config);
        $requestUrl = $oauth->signRequest('GET', 'stream/subscription', $params);
        return $requestUrl;
    }

    /**
     * getPreviewUrl 
     * 
     * @param array $params params 
     * 
     * @return void
     */
    public function getPreviewUrl(array $params)
    {
        $trackId = $params['trackId'];
        unset($params['trackId']);
        $oauthFactory = new OauthFactory();
        $oauth = $oauthFactory->create(1.0, $this->config);
        $requestUrl = $oauth->signRequest('GET', $trackId, $params);
        return $requestUrl;
    }

    /**
     * @param array $params
     * @return mixed
     * @throws MusicProviderException
     */
    public function subscribeUser(array $params)
    {
        $date = new DateTime('now', new DateTimeZone('UTC'));
        $stringDate = $date->format('Y-m-d').'T'.$date->format('H:m:s').'Z';

        if (isset($params['status']) && 'expired' === $params['status']) {
            $expireDate = $date->sub(new DateInterval('P1D'));
        } else {
            $expireDate = $date->add(new DateInterval('P1M'));
        }

        $stringExpireDate = $expireDate->format('Y-m-d').'T'.$expireDate->format('H:m:s').'Z';
        $params = $this->oauth->signRequest('POST', 'user/unlimitedStreaming', 
            array_merge(
                array(
                    'currency' => 'USD',
                    'recurringFee' => 0,
                    'activatedAt' => $stringDate,
                    'currentPeriodStartDate' => $stringDate,
                    'expiryDate' => $stringExpireDate
                ),
                $params
            )
        );

        $response = $this->curl->post($this->config['baseUrl'].'user/unlimitedStreaming', $params);
        if ($response->streaming) {
            return $response;
        } else {
            throw new MusicProviderException('Can\'t subscribe the user', MusicProviderException::INT_CREATE_ACCOUNT_ERROR);
        }
    }

    /**
     * getSubscription 
     * 
     * @param array $params params 
     * 
     * @return void
     */
    public function getSubscription(array $params)
    {
        $url = $this->oauth->signRequest('GET', 'user/unlimitedStreaming', $params);
        $response = $this->curl->get($url);
        if (!is_string($response) && $response->streaming) {
            return $response;
        } else {
            return false;
        }
    }

    /**
     * getFeed 
     * 
     * @param mixed $type type 
     * @param mixed $filename filename 
     * @param mixed $params params 
     * @param mixed $full full 
     * 
     * @return void
     */
    public function getFeed($feedDir, $type, $filename, $params, $full = false)
    {
        $requestUrl = $this->getFeedUrl($type, $params, $full);

        if (!isset($feedDir)) {
            throw new Exception('config entry feedDir is not set.');
        } elseif (!is_writable($feedDir)) {
            throw new Exception('The provided feedDir is not writable');
        }

        $file = fopen($feedDir.'/'.$filename.'.gz', 'w+');

        $this->curl->setOpt(CURLOPT_TIMEOUT, 600000);
        $this->curl->setOpt(CURLOPT_FILE, $file);
        $this->curl->setOpt(CURLOPT_FOLLOWLOCATION, true);
        $response = $this->curl->get($requestUrl);

        fclose($file);

        if ($this->curl->error) {
var_dump(__METHOD__, $this->curl->errorMessage);
            throw new Exception($this->curl->errorMessage, $this->curl->errorCode);
        }

        return true;
    }

    /**
     * getFeedSize 
     * 
     * @param mixed $type type 
     * @param mixed $params params 
     * @param mixed $full full 
     * 
     * @return void
     */
    public function getFeedSize($type, $params, $full = false)
    {
        $requestUrl = $this->getFeedUrl($type, $params, $full = false);
        $this->curl->setOpt(CURLOPT_NOBODY, true);
        $this->curl->setOpt(CURLOPT_HEADER, true);
        $this->curl->setOpt(CURLOPT_RETURNTRANSFER, true );
        $this->curl->setOpt(CURLOPT_FOLLOWLOCATION, true );
        $this->curl->setOpt(CURLOPT_NOBODY, true );
        $response = $this->curl->head($requestUrl);

        return $response;
    }

    /**
     * getFeedUrl 
     * 
     * @param mixed $type type 
     * @param mixed $params params 
     * @param mixed $full full 
     * 
     * @return void
     */
    private function getFeedUrl($type, $params, $full)
    {
        $this->config['baseUrl'] = 'http://feeds.api.7digital.com/1.2/feed/';
        $path = '';
        switch($type){
            case 'artist':
                $path = ($full === true) ? 'artist/full' : 'artist/updates';
                break;
            case "release":
                $path = ($full === true) ? 'release/full' : 'release/updates';
                break;
            case "track":
                $path = ($full === true) ? 'track/full' : 'track/updates';
                break;
        }

        $oauthFactory = new OauthFactory();
        $oauth = $oauthFactory->create(1.0, $this->config);
        $requestUrl = $oauth->signRequest('GET', $path, $params);
        return $requestUrl;
    }

    /**
     * setBaseUrl 
     * 
     * @param mixed $url url 
     * 
     * @return void
     */
    public function setBaseUrl($url)
    {
        $this->config['baseUrl'] = $url;
    }
}
