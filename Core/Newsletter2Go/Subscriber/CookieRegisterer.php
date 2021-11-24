<?php

namespace Newsletter2Go\Subscriber;


use Enlight\Event\SubscriberInterface;
use Shopware\Bundle\CookieBundle\CookieCollection;
use Shopware\Bundle\CookieBundle\Structs\CookieGroupStruct;
use Shopware\Bundle\CookieBundle\Structs\CookieStruct;

class CookieRegisterer implements SubscriberInterface
{

  public static function getSubscribedEvents()
  {
    return [
          'CookieCollector_Collect_Cookies' => 'addTrackingCookies',
        ];
    }

  /**
   * @param \Enlight_Event_EventArgs $args
   */
  public function addTrackingCookies(\Enlight_Event_EventArgs $args)
  {
    try {
        $collection = new CookieCollection();
      $collection->add(new CookieStruct(
        'n2g',
        '/^n2g$/',
        'Newsletter2Go',
        CookieGroupStruct::STATISTICS
      ));

      return $collection;

    } catch (\Exception $exception) {
      Shopware()->Container()->get('pluginlogger')->error($exception->getMessage());
    }
  }
}
