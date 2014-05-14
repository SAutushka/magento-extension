<?php

/**
 * Default Helper
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_supportedVersion = null;
    protected $_supportedMagentoVersion = null;

    public function getHistoricalFlag($websiteId)
    {
        return Mage::app()->getWebsite($websiteId)->getConfig('jirafe_analytics/historicalpull/active', $websiteId) == "true";
    }

    public function setHistoricalFlagOn($websiteId)
    {
        $config = Mage::getConfig();
        $config->saveConfig("jirafe_analytics/historicalpull/active", 'true', 'websites', $websiteId);
        $config->reinit();
    }

    public function setHistoricalFlagOff($websiteId)
    {
        $config = Mage::getConfig();
        $config->saveConfig("jirafe_analytics/historicalpull/active", 'false', 'websites', $websiteId);
        $config->reinit();
    }

    public function fetchBaseCurrencyCode()
    {
        $store = Mage::app()->getStore();
        $baseCurrencyCode = $store->getBaseCurrencyCode();
        return $baseCurrencyCode;
    }

    public function shouldConvertCurrency($currency)
    {
        $store = Mage::app()->getStore();
        $baseCurrency = $store->getBaseCurrency();
        $baseCurrencyCode = $store->getBaseCurrencyCode();
        $rate = $baseCurrency->getRate($currency);
        return (($baseCurrencyCode != $currency) && ($rate));
    }

    /**
     * Converts currency.
     *
     * There appears to be bugs in Magento Core which prevent converting from
     * an arbitrary supported currency to the base currency.  This method
     * attempts to work around this limitation by multiplying the amount by
     * the inverse of the base to foreign conversion rate.
     */
    public function convertCurrency($amount, $currency)
    {
        $base = Mage::app()->getStore()->getBaseCurrency();
        $rate = $base->getRate($currency);
        return (1/$rate)*$amount;
    }

    /**
     * Write log messages to db
     *
     * @param  string $type
     * @param  string $location
     * @param  string $message
     * @param  Exception $exception
     * @return boolean
     */
    public function log($type = null, $location = null, $message = null, $exception = null)
    {
        try {
            if ($exception instanceof Exception) {
                Mage::logException($exception);
            }
            if (Mage::getStoreConfig('jirafe_analytics/debug/logging')) {
                if (Mage::getStoreConfig('jirafe_analytics/debug/type') == 'db') {
                    $log = Mage::getModel('jirafe_analytics/log');
                    $log->setType($type);
                    $log->setLocation($location);
                    $log->setMessage($message);
                    $log->setCreatedDt($this->getCurrentDt());
                    $log->save();
                } else {
                    if (!$exception) {
                        Mage::log($location . ': ' . $message, null, 'jirafe_analytics.log');
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Log server load averages
     *
     * @param  string $message    message to add to log file
     * @return boolean
     * @throws Exception if sys_getloadavg() fails
     */
    public function logServerLoad($location = null)
    {
        /**
         * @var array $load    set of three sampled server load averages
         */

        if (Mage::getStoreConfig('jirafe_analytics/debug/server_load')) {
            try {
                $load = sys_getloadavg();
                if (is_numeric($load[0]) && is_numeric($load[1]) && is_numeric($load[2])) {

                    $message = 'SERVER LOAD AVG: ' . number_format($load[0],2) . ' ' . number_format($load[1],2) . ' '. number_format($load[2],2);

                    if (Mage::getStoreConfig('jirafe_analytics/debug/type') == 'db') {
                        $this->log('DEBUG', $location, $message);
                    } else {
                        Mage::log("$location: $message", null, 'jirafe_analytics.log');
                    }

                    return true;
                } else {
                    $this->log('ERROR', __METHOD__, $e->getMessage(), $e);
                    return false;
                }
            } catch (Exception $e) {
                $this->log('ERROR', __METHOD__, $e->getMessage(), $e);
            }
        }
    }

    /**
     * Current DataTime in UTC/GMT to avoid MySQL possible timezone configuration issues
     *
     * @return string
     */
    public function getCurrentDt()
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Performance tuning options: override server php settings
     *
     * @return void
     */
    public function overridePhpSettings($params=null)
    {
        if (isset($params['max_execution_time'])) {
            $maxExecutionTime = $params['max_execution_time'];
        } else {
            $maxExecutionTime = Mage::getStoreConfig('jirafe_analytics/php/max_execution_time');
        }

        if (isset($params['memory_limit'])) {
            $memoryLimit = $params['memory_limit'];
        } else {
            $memoryLimit = Mage::getStoreConfig('jirafe_analytics/php/memory_limit');
        }

        if (isset($params['proc_nice'])) {
            $procNice = $params['proc_nice'];
        } else {
            $procNice = Mage::getStoreConfig('jirafe_analytics/php/proc_nice');
        }

        /**
         * Set PHP max_execution_time in seconds
         * Excessively large numbers or 0 (infinite) will hurt server performance
         */
        if (is_numeric($maxExecutionTime)) {
            ini_set('max_execution_time', $maxExecutionTime);
            $this->log('DEBUG', __METHOD__, 'max_execution_time = ' . $maxExecutionTime);
        }

        /**
         * Set PHP memory_limit: Number + M (megabyte) or G (gigabyte)
         * Excessively large numbers will hurt server performance
         * Format: 1024M or 1G
         */
        if (strlen($memoryLimit) > 1) {

            ini_set("memory_limit", $memoryLimit);

            if (Mage::getStoreConfig('jirafe_analytics/debug/logging')) {
                $this->log('DEBUG', __METHOD__, 'memory_limit = ' . $memoryLimit);
            }
        }

        /**
         * Set PHP nice value.
         * Lower numbers = lower priority
         */
        try {
            if (is_numeric($procNice)) {
                proc_nice($procNice);
                if (Mage::getStoreConfig('jirafe_analytics/debug/logging')) {
                    $this->log('DEBUG', __METHOD__, 'proc_nice = ' . $procNice);
                }
            }
        } catch (Exception $e) {
            $this->log('ERROR', __METHOD__, $e->getMessage(), $e);
        }
    }

    /**
     * Retrieve Website ID for given store
     * @param int
     * @return int
     */
    public function getWebsiteId($storeId=0)
    {
        if ($storeId) {
            $_store = Mage::getModel('core/store')->load($storeId);
            if ($_store) {
                return $_store->getWebsiteId();
            }
        }
        return 0;
    }

    /**
     * Check whether it is enterprise version or not
     *
     * @return bool
     */
    protected function _isEnterpriseVersion()
    {
        $_moduleLocation = Mage::getRoot() . DS . 'etc' . DS .'modules' . DS .'Enterprise_Enterprise.xml';
        return file_exists($_moduleLocation);
    }

    /**
     * Check whether it is php version supported or not
     *
     * @return bool
     */
    protected function _supportedPhpVersion()
    {
        return version_compare(phpversion(), '5.3.0', '>=');
    }

    /**
     * Check whether it is magento version supported or not
     *
     * @return bool
     */
    protected function _supportMagentoVersion()
    {
        if (is_null($this->_supportedMagentoVersion)) {
            $this->_supportedMagentoVersion = true;
            $_magentoVersion = Mage::getVersion();
            if ($this->_isEnterpriseVersion()) {
                if (version_compare($_magentoVersion, 1.12,'<')) {
                    //if EE version is less than 1.12
                    $this->_supportedMagentoVersion = false;
                }
            } elseif (version_compare($_magentoVersion, 1.7,'<')) {
                //if CE version is less than 1.7
                $this->_supportedMagentoVersion = false;
            }

        }
        return $this->_supportedMagentoVersion;
    }


    /**
     * Check whether the php version and Magento version is within support
     *
     * @return bool
     */
    public function isSupportedVersion()
    {
        if (is_null($this->_supportedVersion)) {
            $this->_supportedVersion = true;
            if (!$this->_supportedPhpVersion()) {
                $this->_supportedVersion = false;
            }
            if (!$this->_supportMagentoVersion()) {
                $this->_supportedVersion = false;
            }

        }
        return $this->_supportedVersion;
    }

    /**
     * error messsage for support version
     * @return string
     */
    public function getErrorMessageForVersion()
    {
        $message = '';
        if (!$this->isSupportedVersion()) {
            $_content = '';
            if (!$this->_supportedPhpVersion()) {
                $_content .= 'PHP 5.3';
            }
            if (!$this->_supportMagentoVersion()) {
                $_content .= ($_content ? ' and ' : '') . 'Magento ';
                $_content .= ($this->_isEnterpriseVersion() ? '1.12' : '1.7');
            }
            $message = $this->__('Jirafe Analytics requires at least ') .
                $this->__($_content) . $this->__(', please update before you start.');
        }
        return $message;
    }
}
