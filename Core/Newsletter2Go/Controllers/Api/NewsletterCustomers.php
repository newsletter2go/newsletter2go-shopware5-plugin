<?php

class Shopware_Controllers_Api_NewsletterCustomers extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\NewsletterCustomer
     */
    protected $resource = null;

    public function init()
    {
        $this->resource = \Shopware\Components\Api\Manager::getResource('NewsletterCustomer');
    }

    /**
     * Get list of customers
     *
     * GET /api/newsletterCustomers/
     */
    public function indexAction()
    {
        $customers = $this->getCustomers();

        $this->View()->assign($customers);
        $this->View()->assign('success', true);
    }

    /**
     * Test connection
     *
     * GET /api/newsletterCustomers/test/
     */
    public function getAction()
    {
        if ($this->Request()->getParam('id') === 'count') {
            $customers = $this->getCustomers();
            $customers['data'] = count($customers['data']);

            $this->View()->assign($customers);
        }

        $this->View()->assign('success', true);
    }

    /**
     * Update customer
     *
     * PUT /api/newsletterCustomers/{email}
     */
    public function putAction()
    {
        $email = $this->Request()->getParam('id');
        $params = $this->Request()->getPost();

        $this->resource->update($email, $params);

        $this->View()->assign(array('success' => true));
    }

    private function getCustomers()
    {

        $devIPs = array("141.16.127.66", "84.159.203.99");
        if (in_array($_SERVER['REMOTE_ADDR'], $devIPs)) {
            error_reporting (E_ALL | E_STRICT);
            ini_set('display_errors',1);
        }

        $subscribed = $this->Request()->getParam('subscribed', false);
        $offset = $this->Request()->getParam('start', false);
        $limit = $this->Request()->getParam('limit', false);
        $group = $this->Request()->getParam('group', false);
        $fields = $this->Request()->getParam('fields', false);
        $emails = $this->Request()->getParam('emails', false);
        $subShopId = $this->Request()->getParam('subShopId', 0);

        $fields = json_decode($fields, true);
        $emails = json_decode($emails, true);
        if (empty($fields)) {
            foreach ($this->resource->getCustomerFields() as $field) {
                $fields[] = $field['id'];
            }
        }

        if (strpos($group, 'campaign_') !== false) {
            $result = $this->getOnlySubscribers(str_replace('campaign_', '', $group), $emails);
        } else {
            $result = $this->resource->getList($subscribed, $offset, $limit, $group, $fields, $emails, $subShopId);
        }

        return $result;
    }

    private function getOnlySubscribers($group, $emails = array())
    {
        $q = 'SELECT ma.email as email, md.firstname AS firstName, md.lastname AS lastName, '
            . 'md.zipcode AS zipCode, md.city AS city, md.street AS street, md.salutation AS salutation '
            . 'FROM s_campaigns_mailaddresses ma '
            . 'LEFT JOIN s_campaigns_maildata md ON ma.email = md.email '
            . 'WHERE ma.email NOT IN (SELECT email FROM s_user) ';

        if ($group) {
            $q .= " AND ma.groupID = $group ";
        }

        if ($emails) {
            $q .= " AND ma.email IN ('" . implode("','", $emails) . "')";
        }

        $subscribers = Shopware()->Db()->fetchAll($q);

        return array('data' => $subscribers);
    }

}
