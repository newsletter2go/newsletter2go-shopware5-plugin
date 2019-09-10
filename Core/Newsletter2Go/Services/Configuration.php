<?php


namespace Newsletter2Go\Services;


use Shopware\Models\Newsletter2Go\Newsletter2Go;

class Configuration
{

    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    private $em;

    public function __construct() {
        $this->em = Shopware()->Models();
    }

    /**
     * Returns config value for $name, returns string if $name value exists,
     * otherwise it returns $default value.
     *
     * @param string $name
     * @param mixed $default
     * @return null | string
     */
    public function getConfigParam($name, $default = null)
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
    public function saveConfigParam($name, $value)
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