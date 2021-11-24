<?php

use Shopware\Models\Newsletter2Go\Newsletter2Go;
use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Frontend_Newsletter2goCallback extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    private $em;

    public function __construct() {
        parent::__construct();
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

    public function indexAction()
    {
        $companyId = $this->Request()->getParam('company_id');

        if (!empty($companyId)) {
            $element = $this->em->getRepository('Shopware\Models\Newsletter2Go\Newsletter2Go')
                ->findOneBy(array('name' => 'companyId'));
            if (!$element) {
                $element = new Newsletter2Go();
                $element->setName('companyId');
            }

            $element->setValue($companyId);
            $this->em->persist($element);
            $this->em->flush();
        }

        header('Content-Type: application/json');
        exit(json_encode(array('success' => true)));

    }
}
