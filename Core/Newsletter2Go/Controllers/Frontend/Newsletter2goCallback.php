<?php

use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Frontend_Newsletter2goCallback extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    private $em;

    public function __construct(
        Enlight_Controller_Request_Request $request,
        Enlight_Controller_Response_Response $response
    ) {
        parent::__construct($request, $response);
        $this->em = Shopware()->Models();
    }

    /**
     * Whitelist notify- and webhook-action for paypal
     */
    public function getWhitelistedCSRFActions()
    {
        return array(
            'index'
        );
    }

    /**
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function indexAction()
    {
        $config = new \Newsletter2Go\Services\Configuration();
        $companyId = $this->Request()->getParam('company_id');
        $userIntegrationId = $this->Request()->getParam('user_integration_id');
        $auth_key = $this->Request()->getParam('auth_key');
        $access_token = $this->Request()->getParam('access_token');
        $refresh_token = $this->Request()->getParam('refresh_token');

        $config->saveConfigParam('companyId', $companyId);
        $config->saveConfigParam('userIntegrationId', $userIntegrationId);
        $config->saveConfigParam('authKey', $auth_key);
        $config->saveConfigParam('accessToken', $access_token);
        $config->saveConfigParam('refreshToken', $refresh_token);

        header('Content-Type: application/json');
        exit(json_encode(array('success' => true)));

    }
}