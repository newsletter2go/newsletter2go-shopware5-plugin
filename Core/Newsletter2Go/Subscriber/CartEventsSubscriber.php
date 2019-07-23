<?php


namespace Newsletter2Go\Subscriber;


use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Action;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Controller_Response_ResponseHttp;
use Newsletter2Go\Services\ApiService;
use Newsletter2Go\Services\Configuration;

class CartEventsSubscriber implements SubscriberInterface
{

    const CART_ENDPOINT = '/users/integrations/{id}/cart/{external_cart_id}';

    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Article' => 'onBackendArticle'
        ];
    }

    public function onAddArticle(\Enlight_Event_EventArgs $arguments)
    {

        // filter Events then if relevant get the payload
//        $arguments->

        /** @var $enlightController Enlight_Controller_Action */
        $enlightController = $arguments->getSubject();

        /** @var $request Enlight_Controller_Request_RequestHttp */
        $request = $arguments->getRequest();

        /** @var $response Enlight_Controller_Response_ResponseHttp */
        $response = $arguments->getResponse();

//TODO:        $this->sendCart();

    }

    private function sendCart($products, $customer, $shopUrl, $cartId)
    {
        $apiService = new ApiService();
        $config = new Configuration();
        $path = str_replace('{id}',$config->getConfigParam('user_integration_id'),self::CART_ENDPOINT);
        $path = str_replace('{external_cart_id}', $cartId, $path);
        $params['body'] = '{}'; // TODO: assemble payload
//        {
//              "cart_id":"11",
//              "shopUrl":"localhost:8096",
//              "products":[
//                  {
//                      "id":"1",
//                      "quantity":"2"
//                  }
//              ],
//              "customer":{
//                  "email":"mimo@newsletter2go.com"
//              }
//        }
        $apiService->httpRequest('PATCH', $path, $params);
    }

}