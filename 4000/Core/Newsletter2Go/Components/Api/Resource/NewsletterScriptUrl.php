<?php
namespace Shopware\Components\Api\Resource;

/**
 * @category  Shopware
 * @package   Shopware\Plugins\n2goExtendApi
 */
class NewsletterScriptUrl extends Resource
{
    /**
     * @param array $params
     * @return boolean
     */
    public function insertUrl(array $params)
    {
        $this->checkPrivilege('insert');
        $this->checkPrivilege('update');

        if (!empty($params['url'])) {
            $urlFromDb = Shopware()->Db()->executeQuery("SELECT 1 FROM `s_core_config_elements` WHERE name = 'newsletter2goScriptUrl'");
			$exists = $urlFromDb->fetch();
			if ($exists === false) {
				Shopware()->Db()->insert('s_core_config_elements', array('form_id' => 0, 'name' => 'newsletter2goScriptUrl', 'value' => $params['url']));				
			} else {
				Shopware()->Db()->update('s_core_config_elements', array('value' => $params['url']), array("name = 'newsletter2goScriptUrl'"));
			}

			return true;
        }

        return false;
    }
}
