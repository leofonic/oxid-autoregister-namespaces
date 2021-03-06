<?php

namespace zunderweb\autoregister_namespaces\core;
use oxRegistry;
use OxidEsales\Eshop\Core\Module\Module;
use OxidEsales\EshopCommunity\Internal\Container\ContainerFactory;
use OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\Bridge\ShopConfigurationDaoBridgeInterface;

class Config extends Config_parent
{
    public function init()
    {
        // Duplicated init protection
        if ($this->_blInit) {
           return;
        }
        parent::init();
        
        //register any module namespaces that are not yet registered
        startProfile('autoregister_namespaces');
        
        $loader = require VENDOR_PATH . 'autoload.php';
        $aPrefixesToLoad = oxRegistry::getUtils()->fromFileCache('zwb_autoload_namespaces');
        if ($aPrefixesToLoad === null || !$this->getConfigParam('blZwarProductionMode')){
            $loadedPrefixes = $loader->getPrefixesPsr4();
            $modulePaths = $this->z_getModulePaths();
            $modulesDir = $this->getModulesDir(true);
            $aPrefixesToLoad = array();
            if (is_array($modulePaths)){
                foreach ($modulePaths as $modulePath){
                    $modulePathAbsolute = $modulesDir . $modulePath;
                    $jsonPath = $modulePathAbsolute . '/composer.json';
                    if(file_exists($jsonPath)){
                        if ($json = file_get_contents($jsonPath)){
                            if ($decoded = json_decode($json, true)){
                                if (isset ($decoded['autoload']) && isset($decoded['autoload']['psr-4'])){
                                    $prefixes = $decoded['autoload']['psr-4'];
                                    if (is_array($prefixes)){
                                        foreach ($prefixes as $prefix => $prefixPath){
                                            if(!isset($loadedPrefixes[$prefix])){
                                                if ($prefixPath == '../../../source/modules/' . $modulePath){
                                                    $aPrefixesToLoad[$prefix] = $modulePathAbsolute;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            oxRegistry::getUtils()->toFileCache('zwb_autoload_namespaces', $aPrefixesToLoad);
        }
        foreach ($aPrefixesToLoad as $prefix => $modulePathAbsolute){
            $loader->addPsr4($prefix, $modulePathAbsolute);
        }
        stopProfile('autoregister_namespaces');
    }
    
    private function z_getModulePaths(): array
    {
        $container = ContainerFactory::getInstance()->getContainer();
        $shopConfiguration = $container->get(ShopConfigurationDaoBridgeInterface::class)->get();

        $modules = [];

        foreach ($shopConfiguration->getModuleConfigurations() as $moduleConfiguration) {
            $module = oxNew(Module::class);
            $path = $moduleConfiguration->getPath();
            $modules[] = $path;
        }

        return $modules;
    }
}