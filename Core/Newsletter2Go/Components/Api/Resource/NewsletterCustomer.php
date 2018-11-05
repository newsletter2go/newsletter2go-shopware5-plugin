<?php

namespace Shopware\Components\Api\Resource;

use Shopware\Models\CustomerStream\Mapping;
use Shopware\Models\Newsletter\Address;
use Doctrine\ORM\Query\Expr;
use Shopware\Models\Newsletter\Group;
use Shopware\Models\Plugin\Plugin;

/**
 * @category  Shopware
 * @package   Shopware\Plugins\n2goExtendApi
 */
class NewsletterCustomer extends Resource
{
    /**
     * Used for migrating fields from \Shopware\Models\Customer\Billing to \Shopware\Models\Customer\Address
     * @var array<string, string>
     */
    private static $addressModelColumnMap = array(
        'firstName' => 'firstname',
        'lastName' => 'lastname',
        'zipCode' => 'zipcode',
    );

    /**
     * @return \Doctrine\ORM\EntityRepository|\Shopware\Models\Customer\Customer
     */
    public function getRepositoryCustomer()
    {
        return $this->getManager()->getRepository('Shopware\Models\Customer\Customer');
    }

    /**
     * @return \Doctrine\ORM\EntityRepository|\Shopware\Models\Newsletter\Address
     */
    public function getRepositoryAddress()
    {
        return $this->getManager()->getRepository('Shopware\Models\Newsletter\Address');
    }

    /**
     * @param bool $subscribed
     * @param bool $offset
     * @param bool $limit
     * @param string $group
     * @param string[] $fields
     * @param string[] $emails
     * @param int $subShopId
     *
     * @return array
     *
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function getList(
        $subscribed = false,
        $offset = false,
        $limit = false,
        $group = '',
        array $fields = array(),
        array $emails = array(),
        $subShopId = 0
    ) {
        $this->checkPrivilege('read');

        $useAddressModel = $this->useAddressModel();
        $billingAddressField = $useAddressModel ? 'defaultBillingAddress' : 'billing';
        $selectFields = array();
        $arrangedFields = $this->arrangeFields($fields);

        $builder = $this->getRepositoryCustomer()
            ->createQueryBuilder('customer')
            ->where('customer.active = true');

        $selectFields[] = 'PARTIAL customer.{' . implode(',', $arrangedFields['customer']) . '}';
        if (!empty($arrangedFields['billing'])) {
            $arrangedFields['billing'][] = 'id';
            $builder->leftJoin('customer.' . $billingAddressField, 'billing');
            $selectFields[] = 'PARTIAL billing.{' . implode(',', $arrangedFields['billing']) . '}';
        }

        if ($subscribed) {
            $builder->andWhere(
                'customer.email IN (SELECT address.email FROM Shopware\Models\Newsletter\Address address)'
            );
        }

        if ($group) {
            $builder->andWhere("customer.groupKey = '$group'");
        }

        if (!empty($emails)) {
            $builder->andWhere("customer.email IN ('" . implode("','", $emails) . "')");
        }

        if ($subShopId) {
            $builder->andWhere('customer.shopId = ' . $subShopId);
        }

        $builder->select($selectFields);

        if ($offset !== false && $limit) {
            $builder->setFirstResult($offset)
                ->setMaxResults($limit);
        }

        $query = $builder->getQuery();
        $query->setHydrationMode($this->getResultMode());
        $pagination = $this->getManager()->createPaginator($query);

        $customers = $pagination->getIterator()->getArrayCopy();

        $customers = $this->fixCustomers($customers, $billingAddressField, $fields);

        return array('data' => $customers);
    }

    /**
     * @param string $email
     * @param array $params
     *
     * @return mixed
     *
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     * @throws \Shopware\Components\Api\Exception\OrmException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws \InvalidArgumentException
     */
    public function update($email, array $params)
    {
        $this->checkPrivilege('update');

        if (empty($email)) {
            throw new \InvalidArgumentException('email-param is missing');
        }

        if ($params['Unsubscribe']) {
            /** @var $article Address */
            $subscription = $this->getRepositoryAddress()->findOneBy(array('email' => $email));
            $this->getManager()->remove($subscription);
            $this->flush();

            return true;
        }

        if ($params['Subscribe']) {
            $groups = Shopware()->Models()->getRepository('Shopware\Models\Newsletter\Group')->findAll();
            if (empty($groups) === false && is_array($groups)) {
                $group = reset($groups);
                $groupId = $group->getId();

                $customer = $this->getRepositoryCustomer()->findOneBy(array('email' => $email));
                $subscription = new Address();

                $subscription->setIsCustomer($customer ? true : false);
                $subscription->setEmail($email);
                $subscription->setGroupId($groupId);

                $om = $this->getManager();
                $om->persist($subscription);
                $om->flush();

                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     *
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function getNewsletterGroups()
    {
        $this->checkPrivilege('read');

        return array_merge(
            $this->getCustomerGroups(),
            $this->getCampaignGroups(),
            $this->getStreamGroups()
        );
    }

    /**
     * @return array
     */
    private function getCustomerGroups()
    {
        return Shopware()->Db()->fetchAll("
            SELECT groupkey AS 'id',
                   description AS 'name',
                   description AS 'description',
                   (
                       SELECT COUNT(*)
                       FROM s_user
                       WHERE s_user.customergroup = s_core_customergroups.groupkey
                   ) AS 'count'
            FROM s_core_customergroups
        ");
    }

    /**
     * @return array
     */
    private function getCampaignGroups()
    {
        return Shopware()->Db()->fetchAll("
            SELECT CONCAT('campaign_', id) AS 'id',
                   name AS 'name',
                   NULL AS 'description',
                   (
                       SELECT count(*)
                       FROM s_campaigns_mailaddresses
                       WHERE s_campaigns_mailaddresses.groupID = s_campaigns_groups.id
                   ) AS 'count'
            FROM s_campaigns_groups
        ");
    }

    /**
     * @return array
     */
    private function getStreamGroups()
    {
        if (!class_exists('Shopware\Models\CustomerStream\CustomerStream')) {
            return array(); // customer streams are not supported
        }

        return Shopware()->Db()->fetchAll("
            SELECT CONCAT('stream_', id) AS 'id',
                   name AS 'name',
                   description AS 'description',
                   0 AS 'count'
            FROM s_customer_streams
        ");
    }

    /**
     * @return string
     *
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function getPluginVersion()
    {
        $this->checkPrivilege('read');

        /** @var Plugin $plugin */
        $plugin = Shopware()->Models()
            ->getRepository('Shopware\Models\Plugin\Plugin')
            ->findOneBy(array('name' => 'Newsletter2Go'));

        return str_replace('.', '', $plugin->getVersion());
    }

    public function getCustomerFields()
    {
        $fields = array();
        $fields[] = $this->createField('id', 'Customer Id.', 'Unique customer identification number', 'Integer');
        $fields[] = $this->createField('email', 'E-mail address');
        $fields[] = $this->createField('active', 'Active', 'Is customer active', 'Boolean');
        $fields[] = $this->createField('accountMode', 'Account mode', '', 'Integer');
        $fields[] = $this->createField('confirmationKey', 'Confirmation key', '', 'String');
        $fields[] = $this->createField('firstLogin', 'First login', '', 'Date');
        $fields[] = $this->createField('lastLogin', 'Last login', '', 'Date');
        $fields[] = $this->createField('groupKey', 'Customer group');
        $fields[] = $this->createField('languageId', 'Language', '', 'Integer');
        $fields[] = $this->createField('paymentPreset', 'Payment preset', '', 'Integer');
        $fields[] = $this->createField('shopId', 'Subshop Id.', '', 'Integer');
        $fields[] = $this->createField('paymentId', 'Price group Id.', '', 'Integer');
        $fields[] = $this->createField('internalComment', 'Internal Comment');
        $fields[] = $this->createField('referer');
        $fields[] = $this->createField('state');
        $fields[] = $this->createField('country');
        $fields[] = $this->createField('subscribed', '', '', 'Boolean');
        $fields[] = $this->createField('failedLogins', 'Failed logins', '', 'Integer');
        $fields[] = $this->createField('billing.company', 'Company');
        $fields[] = $this->createField('billing.department', 'Department');
        $fields[] = $this->createField('billing.salutation', 'Billing salutation');
        $fields[] = $this->createField('billing.firstName', 'Billing firstname');
        $fields[] = $this->createField('billing.lastName', 'Billing lastname');
        $fields[] = $this->createField('billing.street', 'Street');
        $fields[] = $this->createField('billing.zipCode', 'Zipcode');
        $fields[] = $this->createField('billing.city', 'City');
        $fields[] = $this->createField('billing.phone', 'Phone');
        $fields[] = $this->createField('billing.title', 'Title');
        $fields[] = $this->createField('birthday', 'Birthday', '', 'Date');

        if (\Shopware::VERSION >= '5.2') {
            $fields[] = $this->createField('number', 'Customer number');
            $fields[] = $this->createField('salutation', 'Customer salutation');
            $fields[] = $this->createField('firstname', 'Customer firstname');
            $fields[] = $this->createField('lastname', 'Customer lastname');
        } else {
            $fields[] = $this->createField('billing.number', 'Customer number');
        }

        return $fields;
    }

    /**
     * Get stream customers list
     *
     * @param $group
     * @param string[] $emails
     * @param string[] $fields
     *
     * @return array
     */
    public function getStreamList($group, array $emails = array(), array $fields = array())
    {
        $useAddressModel = $this->useAddressModel();
        $billingAddressField = $useAddressModel ? 'defaultBillingAddress' : 'billing';
        $selectFields = array();
        $arrangedFields = $this->arrangeFields($fields);

        $builder = $this->getRepositoryCustomer()
            ->createQueryBuilder('customer')
            ->innerJoin('Shopware\Models\CustomerStream\Mapping', 'mapping', Expr\Join::INNER_JOIN, 'mapping.customerId = customer.id')
            ->where('customer.active = true');

        if ($group) {
            $builder->andWhere('mapping.streamId = ' . $group);
        }

        $selectFields[] = 'PARTIAL customer.{' . implode(',', $arrangedFields['customer']) . '}';
        if (!empty($arrangedFields['billing'])) {
            $arrangedFields['billing'][] = 'id';
            $builder->leftJoin('customer.' . $billingAddressField, 'billing');
            $selectFields[] = 'PARTIAL billing.{' . implode(',', $arrangedFields['billing']) . '}';
        }

        if (!empty($emails)) {
            $builder->andWhere("customer.email IN ('" . implode("','", $emails) . "')");
        }

        $builder->select($selectFields);

        $query = $builder->getQuery();
        $query->setHydrationMode($this->getResultMode());
        $pagination = $this->getManager()->createPaginator($query);

        $customers = $pagination->getIterator()->getArrayCopy();

        $customers = $this->fixCustomers($customers, $billingAddressField, $fields);

        return array('data' => $customers);
    }

    /**
     * Fix customer information
     *
     * @param array $customers
     * @param $billingAddressField
     * @param string[] $fields
     *
     * @return array
     */
    private function fixCustomers($customers, $billingAddressField, $fields)
    {
        $subscribers = null;
        if (in_array('subscribed', $fields, true)) {
            $emails = Shopware()->Db()->fetchAll('SELECT email FROM s_campaigns_mailaddresses');
            $subscribers = array_fill_keys($emails, true);
        }

        $country = $this->getCountry();
        $state = $this->getState();

        foreach ($customers as &$customer) {
            /** @var array $customerBilling */
            $customerBilling = $customer[$billingAddressField];
            unset($customer[$billingAddressField]);

            if (isset($customerBilling['countryId'])) {
                $customer['country'] = $country[$customerBilling['countryId']];
                unset($customerBilling['countryId']);
            }

            $customer['state'] = empty($customerBilling['stateId']) ? '' : $state[$customerBilling['stateId']];
            unset($customerBilling['stateId']);

            foreach ($customerBilling as &$defaultBillingAddress) {
                if ($defaultBillingAddress === null) {
                    $defaultBillingAddress = '';
                }
            }
            unset($defaultBillingAddress);

            if (is_array($subscribers)) {
                $customer['subscribed'] = isset($subscribers[$customer['email']]);
            }

            if (in_array('billing.salutation', $fields, true)) {
                $salutation = strtolower($customerBilling['salutation']);

                if ($salutation === 'mr') {
                    $customerBilling['salutation'] = 'm';
                } else if ($salutation === 'ms') {
                    $customerBilling['salutation'] = 'f';
                }
            }

            if (in_array('billing.birthday', $fields, true) || in_array('birthday', $fields, true)) {
                /** @var $birthday \DateTime */
                $birthday = null;
                if (\Shopware::VERSION >= '5.2' && $customer['birthday'] !== null) {
                    $birthday = $customer['birthday'];
                } else if ($customerBilling['birthday'] !== null) {
                    $birthday = $customerBilling['birthday'];
                }

                $customer['birthday'] = $birthday ? $birthday->format('Y-m-d') : null;
                unset($customerBilling['birthday']);
            }

            if (!empty($arrangedFields['billing'])) {
                unset($customerBilling['id']);
            }

            if (!in_array('id', $fields, true)) {
                unset($customer['id']);
            }

            if ($this->useAddressModel()) {
                foreach (static::$addressModelColumnMap as $returnField => $queriedField) {
                    if (isset($customerBilling[$queriedField])) {
                        $customerBilling[$returnField] = $customerBilling[$queriedField];
                        unset($customerBilling[$queriedField]);
                    }
                }
            }

            $customer['billing'] = $customerBilling;
        }

        return $customers;
    }

    /**
     * @return array
     */
    private function getCountry()
    {
        $countries = Shopware()->Db()->fetchAll('SELECT id, countryname FROM s_core_countries');

        return array_column($countries, 'countryname', 'id');
    }

    /**
     * @return array
     */
    private function getState()
    {
        $states = Shopware()->Db()->fetchAll('SELECT id, name FROM s_core_countries_states');

        return array_column($states, 'name', 'id');
    }

    /**
     * @param string $id
     * @param string $name
     * @param string $description
     * @param string $type
     *
     * @return array
     */
    private function createField($id, $name = '', $description = '', $type = 'String')
    {
        if ($name === '') {
            $name = ucfirst(str_replace('_', ' ', $id));
        }

        return array(
            'id' => $id,
            'name' => $name,
            'description' => $description ?: $name,
            'type' => $type,
        );
    }

    /**
     * @param string[] $fields
     *
     * @return array<string|string[]>
     */
    private function arrangeFields($fields)
    {
        $result = array(
            'billing' => array(),
            'customer' => array('id'),
            'order' => array(),
            'country' => array(),
        );
        $useAddressModel = $this->useAddressModel();

        foreach ($fields as $field) {
            $parts = explode('.', $field);
            switch ($parts[0]) {
                case 'billing':
                    $result['billing'][] = ($useAddressModel && isset(static::$addressModelColumnMap[$parts[1]]))
                        ? static::$addressModelColumnMap[$parts[1]] : $parts[1];
                    break;
                case 'country':
                    $result['billing'][] = 'countryId';
                    break;
                case 'state':
                    $result['billing'][] = 'stateId';
                    break;
                case 'id':
                case 'subscribed':
                    break;
                case 'birthday':
                    if (\Shopware::VERSION >= '5.2') {
                        $result['customer'][] = $field;
                    } else {
                        $result['billing'][] = $field;
                    }
                    break;
                default:
                    $result['customer'][] = $field;
                    break;
            }
        }

        return $result;
    }

    /**
     * @see https://github.com/shopware/shopware/commit/743d006fd9b362a4bcbe5b12b458d54551520ba8
     * @return bool
     */
    private function useAddressModel()
    {
        return \Shopware::VERSION >= '5.3';
    }
}
