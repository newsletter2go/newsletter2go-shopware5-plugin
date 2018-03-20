<?php

class Shopware_Controllers_Api_NewsletterCustomers extends Shopware_Controllers_Api_Rest
{
    /**
     * @var Shopware\Components\Api\Resource\NewsletterCustomer
     */
    protected $resource;

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

    /**
     * @param string $group
     * @param string[] $emails
     *
     * @return array
     */
    private function getOnlySubscribers($group, $emails = array())
    {
        $q = 'SELECT ma.email '
            . 'FROM s_campaigns_mailaddresses ma ';

        if ($group) {
            $q .= " WHERE ma.groupID = $group ";
        }

        if ($emails) {
            $where = strpos($q, 'WHERE') !== false ? 'AND' : 'WHERE';
            $q .= $where . " ma.email IN ('" . implode("','", $emails) . "')";
        }

        $subscribers = Shopware()->Db()->fetchAll($q);

        foreach ($subscribers as $key => $value) {
            $sql = 'SELECT * '
                . 'FROM s_campaigns_maildata '
                . 'WHERE email = \'' . $value['email'] . '\' ';

            $subscriberData = Shopware()->Db()->fetchRow($sql);

            if ($subscriberData != null) {
                $subscribers[$key]['firstName'] = $subscriberData['firstname'];
                $subscribers[$key]['lastName'] = $subscriberData['lastname'];
                $subscribers[$key]['salutation'] = $subscriberData['salutation'];
                $subscribers[$key]['street'] = $subscriberData['street'];
                $subscribers[$key]['zipCode'] = $subscriberData['zipcode'];
                $subscribers[$key]['city'] = $subscriberData['city'];
            }
        }

        return array('data' => $subscribers);
    }
}
