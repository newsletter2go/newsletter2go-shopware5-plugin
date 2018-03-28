<?php

namespace Shopware\Components\Api\Resource;

use Shopware\Models\CustomerStream\Mapping;
use Shopware\Models\Newsletter\Address;
use Doctrine\ORM\Query\Expr;
use Shopware\Models\Newsletter\Group;
use Shopware\Models\Plugin\Plugin;

class Nl2go_ResponseHelper
{
    /**
     * err-number, that should be pulled, whenever credentials are missing
     */
    const ERRNO_PLUGIN_CREDENTIALS_MISSING = 'int-1-404';
    /**
     *err-number, that should be pulled, whenever credentials are wrong
     */
    const ERRNO_PLUGIN_CREDENTIALS_WRONG = 'int-1-403';
    /**
     * err-number for all other (intern) errors. More Details to the failure should be added to error-message
     */
    const ERRNO_PLUGIN_OTHER = 'int-1-600';

    public static function generateErrorResponse($message, $errorCode, $context = null)
    {
        $res = [
            'success' => false,
            'message' => $message,
            'errorcode' => $errorCode,
        ];
        if ($context) {
            $res['context'] = $context;
        }

        return $res;
    }

    public static function generateSuccessResponse(array $data = [])
    {
        $res = ['success' => true, 'message' => 'OK'];

        return array_merge($res, $data);
    }
}

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
    private static $addressModelColumnMap = [
        'firstName' => 'firstname',
        'lastName' => 'lastname',
        'zipCode' => 'zipcode',
    ];

    /**
     * @return \Doctrine\ORM\EntityRepository|\Shopware\Models\Customer\Customer
     */
    public function getRepositoryCustomer()
    {
        return $this->getManager()->getRepository(\Shopware\Models\Customer\Customer::class);
    }

    /**
     * @return \Doctrine\ORM\EntityRepository|\Shopware\Models\Newsletter\Address
     */
    public function getRepositoryAddress()
    {
        return $this->getManager()->getRepository(\Shopware\Models\Newsletter\Address::class);
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
        array $fields = [],
        array $emails = [],
        $subShopId = 0
    ) {
        $this->checkPrivilege('read');

        $useAddressModel = $this->useAddressModel();
        $billingAddressField = $useAddressModel ? 'defaultBillingAddress' : 'billing';
        $selectFields = [];
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

        return ['data' => $customers];
    }

    /**
     * @param string $email
     * @param array $params
     *
     * @return mixed
     *
     * @throws \Shopware\Components\Api\Exception\PrivilegeException
     */
    public function update($email, array $params)
    {
        $this->checkPrivilege('update');

        if (empty($email)) {
            return Nl2go_ResponseHelper::generateErrorResponse(
                'email-param is missing',
                Nl2go_ResponseHelper::ERRNO_PLUGIN_OTHER
            );
        }
        try {
            if ($params['Unsubscribe']) {
                /** @var $article Address */
                $subscription = $this->getRepositoryAddress()->findOneBy(array('email' => $email));
                $this->getManager()->remove($subscription);
                $this->flush();

                return true;
            }

            if ($params['Subscribe']) {
                $groups = Shopware()->Models()->getRepository(Group::class)->findAll();
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
        } catch (\Exception $e) {
            return Nl2go_ResponseHelper::generateErrorResponse(
                $e->getMessage(),
                Nl2go_ResponseHelper::ERRNO_PLUGIN_OTHER
            );
        }
    }

    public function getNewsletterGroups()
    {
        try {
            $this->checkPrivilege('read');
            $groups = Shopware()->Db()->fetchAll(
                'SELECT groupkey as \'id\', description as \'name\', description as \'description\' FROM s_core_customergroups'
            );

            foreach ($groups as &$group) {
                $group['count'] = Shopware()->Db()->fetchOne(
                    "SELECT count(*) as total FROM s_user WHERE customergroup = '{$group['id']}'"
                );
            }
            unset($group);

            $campaignGroups = Shopware()->Db()->fetchAll('SELECT * FROM s_campaigns_groups');
            foreach ($campaignGroups as $campaignGroup) {
                $subsCount = Shopware()->Db()->fetchOne(
                    "SELECT count(*) as total
                    FROM s_campaigns_mailaddresses
                    WHERE groupID = {$campaignGroup['id']}
                      AND email NOT IN (SELECT email FROM s_user)");

                $groups[] = array(
                    'id' => 'campaign_' . $campaignGroup['id'],
                    'name' => $campaignGroup['name'],
                    'description' => null,
                    'count' => $subsCount,
                );
            }

            return $groups;
        } catch (\Exception $e) {
            return Nl2go_ResponseHelper::generateErrorResponse(
                $e->getMessage(),
                Nl2go_ResponseHelper::ERRNO_PLUGIN_OTHER
            );
        }
    }

    public function getPluginVersion()
    {
        try {
            $this->checkPrivilege('read');
            /** @var Plugin $plugin */
            $plugin = Shopware()->Models()
                ->getRepository(Plugin::class)
                ->findOneBy(['name' => 'Newsletter2Go']);

            return str_replace('.', '', $plugin->getVersion());
        } catch (\Exception $e) {
            return Nl2go_ResponseHelper::generateErrorResponse(
                $e->getMessage(),
                Nl2go_ResponseHelper::ERRNO_PLUGIN_OTHER
            );
        }
    }

    public function getCustomerFields()
    {
        $fields = [
            $this->createField('id', 'Customer Id.', 'Unique customer identification number', 'Integer'),
            $this->createField('email', 'E-mail address'),
            $this->createField('active', 'Active', 'Is customer active', 'Boolean'),
            $this->createField('accountMode', 'Account mode', '', 'Integer'),
            $this->createField('confirmationKey', 'Confirmation key', '', 'String'),
            $this->createField('firstLogin', 'First login', '', 'Date'),
            $this->createField('lastLogin', 'Last login', '', 'Date'),
            $this->createField('groupKey', 'Customer group'),
            $this->createField('languageId', 'Language', '', 'Integer'),
            $this->createField('paymentPreset', 'Payment preset', '', 'Integer'),
            $this->createField('shopId', 'Subshop Id.', '', 'Integer'),
            $this->createField('paymentId', 'Price group Id.', '', 'Integer'),
            $this->createField('internalComment', 'Internal Comment'),
            $this->createField('referer'),
            $this->createField('state'),
            $this->createField('country'),
            $this->createField('subscribed', '', '', 'Boolean'),
            $this->createField('failedLogins', 'Failed logins', '', 'Integer'),
            $this->createField('billing.company', 'Company'),
            $this->createField('billing.department', 'Department'),
            $this->createField('billing.salutation', 'Salutation'),
            $this->createField('billing.firstName', 'Firstname'),
            $this->createField('billing.lastName', 'Lastname'),
            $this->createField('billing.street', 'Street'),
            $this->createField('billing.zipCode', 'Zipcode'),
            $this->createField('billing.city', 'City'),
            $this->createField('billing.phone', 'Phone'),
            $this->createField('billing.title', 'Title'),
            $this->createField('birthday', 'Birthday', '', 'Date'),
        ];

        if (\Shopware::VERSION >= '5.2') {
            $fields[] = $this->createField('number', 'Customernumber');
        } else {
            $fields[] = $this->createField('billing.number', 'Customernumber');
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
    public function getStreamList($group, array $emails = [], array $fields = [])
    {
        $useAddressModel = $this->useAddressModel();
        $billingAddressField = $useAddressModel ? 'defaultBillingAddress' : 'billing';
        $selectFields = array();
        $arrangedFields = $this->arrangeFields($fields);

        $builder = $this->getRepositoryCustomer()
            ->createQueryBuilder('customer')
            ->innerJoin(Mapping::class, 'mapping', Expr\Join::INNER_JOIN, 'mapping.customerId = customer.id')
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
