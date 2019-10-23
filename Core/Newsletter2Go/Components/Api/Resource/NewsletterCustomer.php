<?php

namespace Shopware\Components\Api\Resource;

use Shopware\Models\Newsletter\Address;
use Doctrine\ORM\Query\Expr;
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
        $offset = null,
        $limit = null,
        $group = '',
        array $fields = array(),
        array $emails = array(),
        $subShopId = 0
    ) {
        $this->checkPrivilege('read');

        $useAddressModel = $this->compareShopwareVersion('5.3.0');
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

        if (!empty($arrangedFields['attribute'])) {
            $arrangedFields['attribute'][] = 'id';
            $builder->leftJoin('customer.attribute', 'attribute');
            $selectFields[] = 'PARTIAL attribute.{' . implode(',', $arrangedFields['attribute']) . '}';
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

        if ($offset !== null && $limit) {
            $builder->setFirstResult($offset)
                    ->setMaxResults($limit);
        }

        $query = $builder->getQuery();
        $query->setHydrationMode($this->getResultMode());
        $pagination = $this->getManager()->createPaginator($query);

        $customers = $pagination->getIterator()->getArrayCopy();

        $customers = $this->fixCustomers($customers, $billingAddressField, $fields, $subscribed);

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
        return Shopware()->Db()->fetchAll(
            "
            SELECT groupkey AS 'id',
                   description AS 'name',
                   description AS 'description',
                   (
                       SELECT COUNT(*)
                       FROM s_user
                       WHERE s_user.customergroup = s_core_customergroups.groupkey
                   ) AS 'count'
            FROM s_core_customergroups
        "
        );
    }

    /**
     * @return array
     */
    private function getCampaignGroups()
    {
        return Shopware()->Db()->fetchAll(
            "
            SELECT CONCAT('campaign_', id) AS 'id',
                   name AS 'name',
                   NULL AS 'description',
                   (
                       SELECT count(*)
                       FROM s_campaigns_mailaddresses
                       WHERE s_campaigns_mailaddresses.groupID = s_campaigns_groups.id
                   ) AS 'count'
            FROM s_campaigns_groups
        "
        );
    }

    /**
     * @return array
     */
    private function getStreamGroups()
    {
        if (!class_exists('Shopware\Models\CustomerStream\CustomerStream')) {
            return array(); // customer streams are not supported
        }

        return Shopware()->Db()->fetchAll(
            "
            SELECT CONCAT('stream_', id) AS 'id',
                   name AS 'name',
                   description AS 'description',
                   0 AS 'count'
            FROM s_customer_streams
        "
        );
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

        if ($this->compareShopwareVersion('5.2.0')) {
            $fields[] = $this->createField('number', 'Customer number');
            $fields[] = $this->createField('salutation', 'Customer salutation');
            $fields[] = $this->createField('firstname', 'Customer firstname');
            $fields[] = $this->createField('lastname', 'Customer lastname');

            $customFields = $this->getCustomFields();
            $fields = array_merge($fields, $customFields);

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
    public function getStreamList(
        $group,
        array $emails = array(),
        array $fields = array(),
        $limit = null,
        $offset = null,
        $subscribed = false
    ) {
        $useAddressModel = $this->compareShopwareVersion('5.3.0');
        $billingAddressField = $useAddressModel ? 'defaultBillingAddress' : 'billing';
        $selectFields = array();
        $arrangedFields = $this->arrangeFields($fields);


        if($this->compareShopwareVersion('5.6.0')){
            $builder = $this->getManager()->createQueryBuilder();
            $builder->select(array('customer'))
                    ->from('Shopware\Models\Customer\Customer', 'customer')
                    ->innerJoin(
                        'Shopware\Models\CustomerStream\Mapping',
                        'mapping',
                        Expr\Join::WITH,
                        'mapping.customerId = customer.id'
                    )
                    ->where('customer.active = true');

        }else{
            $builder = $this->getRepositoryCustomer()
                            ->createQueryBuilder('customer')
                            ->innerJoin(
                                'Shopware\Models\CustomerStream\Mapping',
                                'mapping',
                                Expr\Join::INNER_JOIN,
                                'mapping.customerId = customer.id'
                            )
                            ->where('customer.active = true');
        };

        if ($group) {
            $builder->andWhere('mapping.streamId = ' . $group);
        }

        $selectFields[] = 'PARTIAL customer.{' . implode(',', $arrangedFields['customer']) . '}';
        if (!empty($arrangedFields['billing'])) {
            $arrangedFields['billing'][] = 'id';
            $builder->leftJoin('customer.' . $billingAddressField, 'billing');
            $selectFields[] = 'PARTIAL billing.{' . implode(',', $arrangedFields['billing']) . '}';
        }

        if (!empty($arrangedFields['attribute'])) {
            $arrangedFields['attribute'][] = 'id';
            $builder->leftJoin('customer.attribute', 'attribute');
            $selectFields[] = 'PARTIAL attribute.{' . implode(',', $arrangedFields['attribute']) . '}';
        }

        if ($subscribed) {
            $builder->andWhere(
                'customer.email IN (SELECT address.email FROM Shopware\Models\Newsletter\Address address)'
            );
        }

        if (!empty($emails)) {
            $builder->andWhere("customer.email IN ('" . implode("','", $emails) . "')");
        }

        if ($offset !== null && $limit) {
            $builder->setFirstResult($offset)
                    ->setMaxResults($limit);
        }

        $builder->select($selectFields);

        $query = $builder->getQuery();
        $query->setHydrationMode($this->getResultMode());
        $pagination = $this->getManager()->createPaginator($query);

        $customers = $pagination->getIterator()->getArrayCopy();

        $customers = $this->fixCustomers($customers, $billingAddressField, $fields, $subscribed);

        return array('data' => $customers);
    }

    /**
     * Fix customer information
     *
     * @param array $customers
     * @param $billingAddressField
     * @param string[] $fields
     * @param bool $subscribed
     *
     * @return array
     */
    private function fixCustomers($customers, $billingAddressField, $fields, $subscribed)
    {

        if (empty($customers)) {
            return $customers;
        }

        $country = $this->getCountry();
        $state = $this->getState();

        $subscriberMails = array();
        //we need all subscribers to determine, which customers are subscribed
        $emails = array_column($customers, 'email');
        $placeholders = implode(', ', array_fill(0, count($emails), '?'));
        $sql = "SELECT email FROM s_campaigns_mailaddresses WHERE email IN ($placeholders)";
        $subscribers = Shopware()->Db()->fetchAll($sql, $emails);
        if (count($subscribers) > 0) {
            $subscriberMails = array_column($subscribers, 'email');
        }

        $fixedCustomers = array();

        foreach ($customers as &$customer) {
            $inSubscriberList = in_array($customer['email'], $subscriberMails);
            if($inSubscriberList){
                $customer['subscribed'] = 1;
            }else{
                $customer['subscribed'] = 0;
            }

            if($subscribed && !$inSubscriberList && $customer['subscribed'] == 0){
                continue;
            }

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

            if (in_array('billing.salutation', $fields, true)) {
                $salutation = strtolower($customerBilling['salutation']);

                if ($salutation === 'mr') {
                    $customerBilling['salutation'] = 'm';
                } else {
                    if ($salutation === 'ms') {
                        $customerBilling['salutation'] = 'f';
                    }
                }
            }

            if (in_array('billing.birthday', $fields, true) || in_array('birthday', $fields, true)) {
                /** @var $birthday \DateTime */
                $birthday = null;
                if ($this->compareShopwareVersion('5.2.0') && $customer['birthday'] !== null) {
                    $birthday = $customer['birthday'];
                } else {
                    if ($customerBilling['birthday'] !== null) {
                        $birthday = $customerBilling['birthday'];
                    }
                }

                $customer['birthday'] = $birthday ? $birthday->format('Y-m-d') : null;
                unset($customerBilling['birthday']);
            }

            if (!in_array('id', $fields, true)) {
                unset($customer['id']);
            }

            if ($this->compareShopwareVersion('5.3.0')) {
                foreach (static::$addressModelColumnMap as $returnField => $queriedField) {
                    if (isset($customerBilling[$queriedField])) {
                        $customerBilling[$returnField] = $customerBilling[$queriedField];
                        unset($customerBilling[$queriedField]);
                    }
                }
            }

            $customer['billing'] = $customerBilling;

            if ($this->compareShopwareVersion('5.2.0')) {
                $customer['attribute'] = $this->getCustomerCustomFields($customer);
            }

            $fixedCustomers[] = $customer;
        }

        return $fixedCustomers;
    }

    private function getCustomFields()
    {
        $fields = array();

        try {
            $crudService = Shopware()->Container()->get('shopware_attribute.crud_service');
            $customerCustomAttributesList = $crudService->getList('s_user_attributes');
            $unwantedFields = array('id', 'userID');
            $datatypes = array(
                'boolean',
                'date',
                'integer',
                'double',
                'datetime'
            );
            foreach ($customerCustomAttributesList as $attribute) {

                $columnName = $attribute->getColumnName();

                if (in_array($columnName, $unwantedFields)) {
                    continue;
                }

                $type = in_array($attribute->getColumnType(), $datatypes) ? ucfirst(
                    $attribute->getColumnType()
                ) : 'String';

                $fields[] = $this->createField(
                    'attribute.' . $columnName,
                    $attribute->getLabel(),
                    $attribute->getLabel(),
                    $type
                );
            }

            if (is_null($fields)) {
                $fields = array();
            }

        } catch (\Exception $exception) {

        }

        return $fields;
    }

    private function getCustomerCustomFields($customer)
    {
        $fields = array();
        $availableCustomFields = $this->getCustomFields();

        if (empty($availableCustomFields) || !is_array($availableCustomFields)) {
            return $fields;
        }

        foreach ($availableCustomFields as $field) {
            $fieldParts = explode('.', $field['id']);
            $fieldName = $fieldParts[1];
            if (isset($customer['attribute'][$fieldName])) {
                $customerField = $customer['attribute'][$fieldParts[1]];

                switch ($field['type']) {
                    case 'Date':
                        $fields[$fieldName] = $customerField->format('Y-m-d');
                        break;
                    case 'Datetime';
                        $fields[$fieldName] = $customerField->format('Y-m-d H:i:s');
                        break;
                    default:
                        $fields[$fieldName] = $customerField;
                }

            } else {
                $fields[$fieldParts[1]] = null;
            }
        }

        return $fields;
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
            'attribute' => array()
        );
        $useAddressModel = $this->compareShopwareVersion('5.3.0');

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
                    if ($this->compareShopwareVersion('5.2.0')) {
                        $result['customer'][] = $field;
                    } else {
                        $result['billing'][] = $field;
                    }
                    break;
                case 'attribute':
                    if ($this->compareShopwareVersion('5.2.0')) {
                        $result['attribute'][] = $parts[1];
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
    private function compareShopwareVersion($compareVersion)
    {
        return version_compare(Shopware()->Container()->get('config')->get('version'), $compareVersion, '>=') && Shopware()
                ->Container()
                ->get('config')
                ->get('version') !== '___VERSION___';
    }
}
