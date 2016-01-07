<?php

/**
 * Log Model
 * 
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Model_Log extends Jirafe_Analytics_Model_Abstract
{
    /**
     * Class construction & resource initialization
     */
    
    protected function _construct()
    {
        $this->_init('jirafe_analytics/log');
    }
    
    /**
     * Purge data
     *
     * @return boolean
     * @throws Exception if failure to purge data
     */
    public function purgeData( $minutes = null )
    {
        try {
            if ( !is_numeric($minutes) ) {
                $minutes = Mage::getStoreConfig('jirafe_analytics/debug/purge_time');
            }
            
            if ( is_numeric($minutes) ) {
                $resource = Mage::getSingleton('core/resource');
                $sql = sprintf("DELETE FROM %s WHERE TIMESTAMPDIFF(MINUTE,created_dt,'%s') > %d",
                                    $resource->getTableName('jirafe_analytics/log'),
                                    Mage::helper('jirafe_analytics')->getCurrentDt(),
                                    $minutes);
                $connection = $resource->getConnection('core_write')->query($sql);
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Log::purgeData()', $e->getMessage(), $e);
            return false;
        }
    }
}
