<?php

class Shopware_Controllers_Api_NewsletterCustomers extends Shopware_Controllers_Api_Rest
{
    /**
     * @var \Shopware\Components\Api\Resource\NewsletterCustomer
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
        try {
            $email = $this->Request()->getParam('id');
            $params = $this->Request()->getPost();

            $this->resource->update($email, $params);

            $this->View()->assign(array('success' => true));
        } catch (\Exception $e) {
            $this->View()->assign(
                array(
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errorcode' => \Shopware_Plugins_Core_Newsletter2Go_Bootstrap::ERRNO_PLUGIN_OTHER,
                )
            );
        }
    }

    /**
     * @return array
     *
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     */
    private function getCustomers()
    {
        $subscribed = $this->Request()->getParam('subscribed', false);
        $offset = $this->Request()->getParam('start', false);
        $limit = $this->Request()->getParam('limit', false);
        $group = $this->Request()->getParam('group', false);
        $fields = $this->Request()->getParam('fields', array());
        $emails = $this->Request()->getParam('emails', array());
        $subShopId = $this->Request()->getParam('subShopId', 0);

        $fields = (array)json_decode($fields, true);
        $emails = (array)json_decode($emails, true);
        if (empty($fields)) {
            $fields = array_column($this->resource->getCustomerFields(), 'id');
        }

        if (strpos($group, 'campaign_') !== false) {
            $result = $this->getOnlySubscribers(str_replace('campaign_', '', $group), $emails,  $limit, $offset);
        } else {
            if (strpos($group, 'stream_') !== false) {
                $result = $this->getOnlyStreamCustomers(str_replace('stream_', '', $group), $emails, $limit = NULL, $offset = NULL);
            } else {
                $result = $this->resource->getList($subscribed, $offset, $limit, $group, $fields, $emails, $subShopId);
            }
        }

        return $result;
    }

    /**
     * @param string $group
     * @param string[] $emails
     *
     * @return array
     */
    private function getOnlySubscribers($group, array $emails = array(),  $limit = NULL, $offset = NULL)
    {
        $q = 'SELECT ma.email FROM s_campaigns_mailaddresses ma WHERE 1';

        if ($group) {
            $q .= " AND ma.groupID = $group";
        }

        if ($emails) {
            $q .= " AND ma.email IN ('" . implode("','", $emails) . "')";
        }

        if ($limit) {
            $q .= "LIMIT = $limit";
        }

        if ($offset) {
            $q .= "OFFSET = $offset";
        }

        $subscribers = Shopware()->Db()->fetchAll($q);

        foreach ($subscribers as &$subscriber) {
            $sql = "SELECT * FROM s_campaigns_maildata WHERE email = '{$subscriber['email']}'";
            $subscriberData = Shopware()->Db()->fetchRow($sql);

            if ($subscriberData) {
                $subscriber['firstName'] = $subscriberData['firstname'];
                $subscriber['lastName'] = $subscriberData['lastname'];
                $subscriber['salutation'] = $subscriberData['salutation'];
                $subscriber['street'] = $subscriberData['street'];
                $subscriber['zipCode'] = $subscriberData['zipcode'];
                $subscriber['city'] = $subscriberData['city'];
            }
        }

        return array('data' => $subscribers);
    }

    /**
     * Get only customers for specific stream
     *
     * @param string $group
     * @param string[] $emails
     * @param string[] $fields
     *
     * @return array
     */
    private function getOnlyStreamCustomers($group, array $emails = array(), array $fields = array(), $limit = NULL, $offset = NULL)
    {
        return $this->resource->getStreamList($group, $emails, $fields, $limit = NULL, $offset = NULL);
    }
}
