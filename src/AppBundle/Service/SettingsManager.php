<?php

namespace AppBundle\Service;

use AppBundle\Utils\Settings;
use Craue\ConfigBundle\Util\Config as CraueConfig;
use Doctrine\Common\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class SettingsManager
{
    private $craueConfig;
    private $configEntityName;
    private $doctrine;
    private $logger;

    private $settings = [
        'brand_name',
        'administrator_email',
        'stripe_test_publishable_key',
        'stripe_test_secret_key',
        'stripe_test_connect_client_id',
        'stripe_live_publishable_key',
        'stripe_live_secret_key',
        'stripe_live_connect_client_id',
        'stripe_livemode',
        'google_api_key',
        'latlng',
        'default_tax_category',
        'currency_code',
    ];

    private $secretSettings = [
        'stripe_test_publishable_key',
        'stripe_test_secret_key',
        'stripe_test_connect_client_id',
        'stripe_live_publishable_key',
        'stripe_live_secret_key',
        'stripe_live_connect_client_id',
        'google_api_key',
    ];

    public function __construct(CraueConfig $craueConfig, $configEntityName, ManagerRegistry $doctrine, LoggerInterface $logger)
    {
        $this->craueConfig = $craueConfig;
        $this->configEntityName = $configEntityName;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function isSecret($name)
    {
        return in_array($name, $this->secretSettings);
    }

    public function get($name)
    {
        switch ($name) {
            case 'stripe_publishable_key':
                $name = $this->isStripeLivemode() ? 'stripe_live_publishable_key' : 'stripe_test_publishable_key';
                break;
            case 'stripe_secret_key':
                $name = $this->isStripeLivemode() ? 'stripe_live_secret_key' : 'stripe_test_secret_key';
                break;
            case 'stripe_connect_client_id':
                $name = $this->isStripeLivemode() ? 'stripe_live_connect_client_id' : 'stripe_test_connect_client_id';
                break;
        }

        try {
            return $this->craueConfig->get($name);
        } catch (\RuntimeException $e) {}
    }

    private function isStripeLivemode()
    {
        $livemode = $this->get('stripe_livemode');

        if (!$livemode) {
            return false;
        }

        return filter_var($livemode, FILTER_VALIDATE_BOOLEAN);
    }

    public function set($name, $value)
    {
        $className = $this->configEntityName;

        $setting = $this->doctrine
            ->getRepository($className)
            ->findOneBy([
                'name' => $name
            ]);

        if (!$setting) {

            $setting = new $className();
            $setting->setSection('general');
            $setting->setName($name);

            $this->doctrine
                ->getManagerForClass($className)
                ->persist($setting);
        }

        $setting->setValue($value);
    }

    public function flush()
    {
        $this->doctrine->getManagerForClass($this->configEntityName)->flush();
    }

    public function isFullyConfigured()
    {
        foreach ($this->settings as $name) {
            try {
                $this->craueConfig->get($name);
            } catch (\RuntimeException $e) {
                return false;
            }
        }

        return true;
    }

    public function asEntity()
    {
        $settings = new Settings();

        foreach ($this->settings as $name) {
            try {
                $value = $this->craueConfig->get($name);
                $settings->$name = $value;
            } catch (\RuntimeException $e) {}
        }

        return $settings;
    }
}
