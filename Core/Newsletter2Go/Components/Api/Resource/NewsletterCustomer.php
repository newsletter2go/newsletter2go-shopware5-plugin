<?php

namespace Shopware\Components\Api\Resource;

use Shopware\Components\Api\Exception as ApiException;
use Shopware\Models\Newsletter\Address;

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


    static function generateErrorResponse($message, $errorCode, $context = null)
    {
        $res = array(
            'success'   => false,
            'message'   => $message,
            'errorcode' => $errorCode,
        );
        if ($context != null) {
            $res['context'] = $context;
        }

        return $res;
    }

    static function generateSuccessResponse($data = array())
    {
        $res = array('success' => true, 'message' => 'OK');

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
    private $addressModelColumnMap = [
        'firstName' => 'firstname',
        'lastName' => 'lastname',
        'zipCode' => 'zipcode',
    ];

    /**
     * @return \Doctrine\ORM\EntityRepository|\Shopware\Models\Customer\Repository
     */
    public function getRepositoryCustomer()
    {
        return $this->getManager()->getRepository('Shopware\Models\Customer\Customer');
    }

    /**
     * @return \Doctrine\ORM\EntityRepository|\Shopware\Models\Newsletter\Repository
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
     * @param array $fields
     * @param array $emails
     * @param int $subShopId
     * @return array
     */
    public function getList($subscribed = false, $offset = false, $limit = false, $group = '', $fields = array(), $emails = array(), $subShopId = 0)
    {
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
            $builder->andWhere('customer.email IN (SELECT address.email FROM Shopware\Models\Newsletter\Address address)');
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

        $country = array();
        $countries = Shopware()->Db()->fetchAll('SELECT countryname FROM s_core_countries');
        foreach ($countries as $c) {
            $country[$c['id']] = $c['countryname'];
        }

        $hasId = in_array('id', $fields);
        $hasSubs = in_array('subscribed', $fields);
        $hasSalutation = in_array('billing.salutation', $fields);
        $hasBirthday = in_array('billing.birthday', $fields) || in_array('birthday', $fields);
        if ($hasSubs) {
            $emails = Shopware()->Db()->fetchAll('SELECT email FROM s_campaigns_mailaddresses');
            $subscribers = array();
            foreach ($emails as $e) {
                $subscribers[$e['email']] = true;
            }
        }

        $state = array();
        $states = Shopware()->Db()->fetchAll('SELECT name FROM s_core_countries_states');
        foreach ($states as $s) {
            $state[$s['id']] = $s['name'];
        }

        foreach ($customers as &$customer) {
            $billing = $customer[$billingAddressField];
            unset($customer[$billingAddressField]);
            $customer['billing'] = $billing;

            if (isset($customer['billing']['countryId'])) {
                $customer['country'] = $country[$customer['billing']['countryId']];
                unset($customer['billing']['countryId']);
            }

            $customer['state'] = empty($customer['billing']['stateId']) ? '' : $state[$customer['billing']['stateId']];
            unset($customer['billing']['stateId']);

            foreach ($customer['billing'] as &$defaultBillingAddress) {
                if (is_null($defaultBillingAddress)) {
                    $defaultBillingAddress = '';
                }
            }

            if ($hasSubs) {
                $customer['subscribed'] = isset($subscribers[$customer['email']]);
            }

            if ($hasSalutation) {
                $salutation = strtolower($customer['billing']['salutation']);

                if ($salutation === 'mr') {
                    $customer['billing']['salutation'] = 'm';
                } else if ($salutation === 'ms') {
                    $customer['billing']['salutation'] = 'f';
                }
            }

            if ($hasBirthday) {
                /** @var $birthday \DateTime */
                $birthday = null;
                if (\Shopware::VERSION >= '5.2' && $customer['birthday'] !== null) {
                    $birthday = $customer['birthday'];
                } else if ($customer['billing']['birthday'] !== null) {
                    $birthday = $customer['billing']['birthday'];
                }

                $customer['birthday'] = $birthday ? $birthday->format('Y-m-d') : null;
                unset($customer['billing']['birthday']);
            }

            if (!empty($arrangedFields['billing'])) {
                unset($customer['billing']['id']);
            }

            if (!$hasId) {
                unset($customer['id']);
            }

            if ($useAddressModel) {
                foreach ($this->addressModelColumnMap as $returnField => $queriedField) {
                    if (isset($customer['billing'][$queriedField])) {
                        $customer['billing'][$returnField] = $customer['billing'][$queriedField];
                        unset($customer['billing'][$queriedField]);
                    }
                }
            }
        }

        return array('data' => $customers);
    }

    /**
     * @param string $email
     * @param array $params
     * @return mixed
     * @throws \Shopware\Components\Api\Exception\ValidationException
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
     * @throws \Shopware\Components\Api\Exception\CustomValidationException
     */
    public function update($email, array $params)
    {
        $this->checkPrivilege('update');

        if (empty($email)) {
            return Nl2go_ResponseHelper::generateErrorResponse('email-param is missing', Nl2go_ResponseHelper::ERRNO_PLUGIN_OTHER);
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
        } catch (\Exception $e) {
            return Nl2go_ResponseHelper::generateErrorResponse($e->getMessage(), Nl2go_ResponseHelper::ERRNO_PLUGIN_OTHER);
        }
    }

    public function getNewsletterGroups()
    {
        try {
            $this->checkPrivilege('read');
            $groups = Shopware()->Db()->fetchAll('SELECT groupkey as \'id\', description as \'name\', description as \'description\' FROM s_core_customergroups');

            foreach ($groups as &$group) {
                $group['count'] = Shopware()->Db()->fetchOne("SELECT count(*) as total FROM s_user WHERE customergroup = '{$group['id']}'");
            }

            $campaignGroups = Shopware()->Db()->fetchAll('SELECT * FROM s_campaigns_groups');
            foreach ($campaignGroups as $campaignGroup) {
                $subsCount = Shopware()->Db()->fetchOne("SELECT count(*) as total
                    FROM s_campaigns_mailaddresses
                    WHERE groupID = {$campaignGroup['id']}");

                $groups[] = array(
                    'id'          => 'campaign_' . $campaignGroup['id'],
                    'name'        => $campaignGroup['name'],
                    'description' => null,
                    'count'       => $subsCount,
                );
            }

            return $groups;
        } catch (\Exception $e) {
            return Nl2go_ResponseHelper::generateErrorResponse($e->getMessage(), Nl2go_ResponseHelper::ERRNO_PLUGIN_OTHER);
        }
    }

    public function getPluginVersion()
    {
        try {
            $this->checkPrivilege('read');
            /** @var \Shopware\Models\Plugin\Plugin $plugin */
            $plugin = Shopware()->Models()->getRepository('Shopware\Models\Plugin\Plugin')->findOneBy(array('name' => 'Newsletter2Go'));

            return str_replace('.', '', $plugin->getVersion());
        } catch (\Exception $e) {
            return Nl2go_ResponseHelper::generateErrorResponse($e->getMessage(), Nl2go_ResponseHelper::ERRNO_PLUGIN_OTHER);
        }
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
        $fields[] = $this->createField('billing.salutation', 'Salutation');
        $fields[] = $this->createField('billing.firstName', 'Firstname');
        $fields[] = $this->createField('billing.lastName', 'Lastname');
        $fields[] = $this->createField('billing.street', 'Street');
        $fields[] = $this->createField('billing.zipCode', 'Zipcode');
        $fields[] = $this->createField('billing.city', 'City');
        $fields[] = $this->createField('billing.phone', 'Phone');
        $fields[] = $this->createField('billing.title', 'Title');
        $fields[] = $this->createField('birthday', 'Birthday', '', 'Date');

        if (\Shopware::VERSION >= '5.2') {
            $fields[] = $this->createField('number', 'Customernumber');
        } else {
            $fields[] = $this->createField('billing.number', 'Customernumber');
        }

        return $fields;
    }

    private function createField($id, $name = '', $description = '', $type = 'String')
    {
        if ($name === '') {
            $name = ucfirst(str_replace('_', ' ', $id));
        }

        return array(
            'id'          => $id,
            'name'        => $name,
            'description' => $description ? $description : $name,
            'type'        => $type,
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
            'billing'  => array(),
            'customer' => array('id'),
            'order'    => array(),
            'country'  => array(),
        );
        $useAddressModel = $this->useAddressModel();

        foreach ($fields as $field) {
            $parts = explode('.', $field);
            switch ($parts[0]) {
                case 'billing':
                    $result['billing'][] = ($useAddressModel && isset($this->addressModelColumnMap[$parts[1]]))
                        ? $this->addressModelColumnMap[$parts[1]] : $parts[1];
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
