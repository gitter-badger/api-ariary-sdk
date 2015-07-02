<?php namespace ApiAriary;


use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\ErrorEvent;
use GuzzleHttp\Event\EventInterface;
use GuzzleHttp\Event\SubscriberInterface;


/**
 * Class OAuthExchange
 *
 * Event Subscriber for HTTP request to deal with the
 * API Ariary OAuth System (AAOS)
 *
 * @package ApiAriary
 */
class OAuthExchange implements SubscriberInterface{

    protected $clientId;
    protected $clientSecret;
    protected $token;

    public function getEvents(){
        return [
            'before'   => ['onBefore', 100],
            'complete' => ['onComplete'],
            'error'    => ['onError']
        ];
    }

    public function onBefore(BeforeEvent $e, $name){
        //@todo : vérifie nullité des paramètres client (client_id, client_secret) => stop si null ou vide
        //echo($e->getRequest());
    }

    public function onComplete(CompleteEvent $e, $name){
        //echo "\n";
    }

    public function onError(ErrorEvent $e, $name){

        if(!is_object($e) || !is_object($e->getResponse()))
            throw new \ErrorException('Error System', 500);

        //Intercept OAuth restriction : 401 OAuth Restriction, Unauthorized requaest
        if ($e->getResponse()->getStatusCode() == 401) {

            //Token Access Request
            $this->performOAuth($e, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            //Intercept response
            $newResponse = $e->getClient()->send($e->getRequest());
            $e->intercept($newResponse);
        }
    }

    protected function getUri($e, $path = null){
        $uri = sprintf("%s://%s",
            $e->getRequest()->getScheme(),
            $e->getRequest()->getHost()
        );
        return $uri . (is_null($path) ? '' : $path);
    }

    /**
     * Deal with OAuth mecanism / exchange user config to a token passport
     *
     * @param EventInterface $e
     * @param $input
     */
    protected function performOAuth(EventInterface $e, $input){
        //build request to api oauth
        $response = $e->getClient()->post($this->getUri($e, '/oauth/token'), [
            'body' => $input
        ]);

        //decode response to retrieve token array
        $token = json_decode($response->getBody()->getContents(), true);

        //store token
        $storage = new TokenStorage($this->clientSecret, dirname(dirname(dirname(__FILE__))) . '/storage');
        $storage->store($this->clientId, $token['access_token']);

        //set token information to header
        $e->getRequest()->setHeader('Authorization', 'Bearer ' . $token['access_token']);
    }

    /**
     * Set client configurations
     *
     * @param $clientId
     * @param $clientSecret
     * @param $token
     * @return $this
     */
    public function setClientOptions($clientId, $clientSecret, $token){
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->token = $token;

        return $this;
    }
}