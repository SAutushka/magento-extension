<?php

/**
 * Historical Batch Create Job
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    John "The Man" Connor (john.connor@jirafe.com)
 */
class Jirafe_Analytics_Model_HistoricalBatchCreate extends Jirafe_Analytics_Model_Base
{
    private static $MODELS = array('category', 'customer', 'employee', 'order', 'product');

    public function create()
    {
        try
        {
            Mage::helper('jirafe_analytics')->log('DEBUG', __METHOD__, 'Starting Historical Create Batch Job.');
            $websiteIds = $this->activeWebsites();
            if (count($websiteIds) == 0) {
                return;
            }
            $data = array(
                'max_execution_time' => '1800',
                'memory_limit' => '2048M',
                'proc_nice' => '16'
            );
            $history = Mage::getModel('jirafe_analytics/data');

            foreach ($websiteIds as $websiteId) {
                $keepWorking = false;

                while (!$this->isTimeUp()) {
                    $keepWorking = $history->convertHistoricalData(Jirafe_Analytics_Model_HistoricalBatchCreate::$MODELS, $websiteId, $data);
                    Mage::helper('jirafe_analytics')->log('DEBUG', __METHOD__, 'Finished converting the historical data.');
                    if(!$keepWorking) {
                        break;
                    }
                }
                if (!$keepWorking) {
                    Mage::helper('jirafe_analytics')->log('DEBUG', __METHOD__, 'keepWorking is false.');
                    Mage::helper('jirafe_analytics')->setHistoricalFlagOff($websiteId);
                    Mage::getModel('jirafe_analytics/curl')->updateHistoricalPushStatus($websiteId, 'complete');
                }
            }
            Mage::helper('jirafe_analytics')->log('DEBUG', __METHOD__, 'Stoping Historical Create Batch Job.');
        }
        catch (Exception $e)
        {
            Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
        }
    }
}

