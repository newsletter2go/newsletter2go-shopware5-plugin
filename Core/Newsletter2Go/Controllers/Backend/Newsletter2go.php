<?php

use Shopware\Models\Newsletter2Go\Newsletter2Go;
use Newsletter2Go\Services\Environment;
use Newsletter2Go\Services\Cryptography;

class Shopware_Controllers_Backend_Newsletter2go extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    private $em;

    /**
     * @param Enlight_Controller_Request_Request $request
     * @param Enlight_Controller_Response_Response $response
     *
     * @throws \Exception
     */
    public function __construct(
        Enlight_Controller_Request_Request $request,
        Enlight_Controller_Response_Response $response
    ) {
        parent::__construct($request, $response);

        $this->em = Shopware()->Models();
    }

    /**
     * Default index action
     *
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function indexAction()
    {
        if (!$this->getConfigParam('apiUsername')) {
            $this->createApiUser();
        }

        $this->View()->loadTemplate('backend/newsletter2go/app.js');
    }

    /**
     * Returns shop api credentials and Newsletter2Go clients API key
     */
    public function getDataAction()
    {
        $data = array();
        /* @var Newsletter2Go[] $elements */
        $elements = $this->em->getRepository('Shopware\Models\Newsletter2Go\Newsletter2Go')->findAll();
        foreach ($elements as $element) {
            $data[$element->getName()] = $element->getValue();
        }

        $data['baseUrl'] = Shopware()->Modules()->Core()->sRewriteLink();
        $this->View()->assign(
            array(
                'success' => true,
                'data' => $data
            )
        );
    }

    /**
     * Resets API settings
     *
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function resetApiUserAction()
    {
        $this->deleteApiUser();
        $this->createApiUser();
        $this->getDataAction();
    }

    /**
     * Saves conversion tracking in database
     *
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function setTrackingAction()
    {
        $trackOrders = $this->getConfigParam('trackOrders');
        $trackOrders = $trackOrders ? 0 : 1;
        $this->saveConfigParam('trackOrders', $trackOrders);
        $this->em->flush();
        $this->getDataAction();
    }

    /**
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function createApiUser()
    {   
        $enviroment = new Environment();
	$cryptography = new Cryptography();
	    
        $apiUser = new \Shopware\Models\User\User();
        $apiUser->setName('newsletter2goApiUser');
        $apiUser->setUsername('newsletter2goApiUser');
        $apiUser->setApiKey(($cryptography($enviroment)->generateRandomString()));

        if (method_exists($apiUser, 'setEncoder')) {
            $apiUser->setEncoder('md5');
        }

        /** @var Shopware\Models\User\Role $adminRole */
        $adminRole = $this->em->getRepository('Shopware\Models\User\Role')->findOneBy(array('admin' => 1));
        $apiUser->setLocaleId(0);
        $apiUser->setPassword(md5(time()));
        $apiUser->setRole($adminRole);
        $this->em->persist($apiUser);

        $this->saveConfigParam('apiUsername', 'newsletter2goApiUser');
        $this->saveConfigParam('apiKey', $apiUser->getApiKey());

        $this->em->flush();
    }

    /**
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function deleteApiUser()
    {
        $user = $this->em->getRepository('Shopware\Models\User\User')->findOneBy(
            array('username' => 'newsletter2goApiUser')
        );
        $configUsername = $this->em->getRepository('Shopware\Models\Newsletter2Go\Newsletter2Go')->findOneBy(
            array('name' => 'apiUsername')
        );
        $configApiKey = $this->em->getRepository('Shopware\Models\Newsletter2Go\Newsletter2Go')->findOneBy(
            array('name' => 'apiKey')
        );

        if ($user) {
            $this->em->remove($user);
        }

        if ($configUsername) {
            $this->em->remove($configUsername);
        }

        if ($configApiKey) {
            $this->em->remove($configApiKey);
        }

        $this->em->flush();
    }

    /**
     * Returns config value for $name, returns string if $name value exists,
     * otherwise it returns $default value.
     *
     * @param string $name
     * @param mixed $default
     * @return null | string
     */
    private function getConfigParam($name, $default = null)
    {
        $value = $this->em->getRepository('Shopware\Models\Newsletter2Go\Newsletter2Go')
                          ->findOneBy(array('name' => $name));

        return $value ? $value->getValue() : $default;
    }

    /**
     * Saves new value to newsletter2go table or updates existing one
     *
     * @param string $name
     * @param string $value
     *
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function saveConfigParam($name, $value)
    {
        $element = $this->em->getRepository('Shopware\Models\Newsletter2Go\Newsletter2Go')
                            ->findOneBy(array('name' => $name));
        if (!$element) {
            $element = new Newsletter2Go();
            $element->setName($name);
        }

        $element->setValue($value);
        $this->em->persist($element);
        $this->em->flush();
    }

}
