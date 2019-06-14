<?php

namespace BucoPartDocuments\Services;

use Shopware\Components\Plugin\CachedConfigReader;

class Config
{
    /**
     * @var CachedConfigReader
     */
    private $configReader;
    /**
     * @var string
     */
    private $pluginName;

    /** @var array */
    private $store;

    public function __construct(CachedConfigReader $configReader, string $pluginName)
    {
        $this->configReader = $configReader;
        $this->pluginName = $pluginName;

        $this->loadPluginConfig();
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function get(string $name)
    {
        return $this->store[$name];
    }

    /**
     * Load and process config value restrictions and conversions.
     *
     * @return void
     */
    private function loadPluginConfig()
    {
        $this->store = $this->configReader->getByPluginName($this->pluginName);
    }
}
