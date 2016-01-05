<?php

/**
 * Current Batch Job Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    John "The Man" Connor (john.connor@jirafe.com)
 */

class Jirafe_Analytics_Model_BatchPush extends Jirafe_Analytics_Model_Base
{
    protected function makeCallback()
    {
        $me = $this;
        $callback = function($batch) use ($me) {
            $id = $batch->getId();
            $json = $batch->getJson();
            if ($batch->hasRawData()) {
                $websiteId = $batch->getWebsiteId();
                $data = array(array('id' => $id, 'website_id' => $websiteId, 'json' => $json));
                $response = $me->sendToJirafe($data);
                $me->updateBatchAttemptStatuses($response);
            }
            return $me->isTimeUp();
        };
        return $callback;
    }

    public function push()
    {
        try
        {
            Mage::helper('jirafe_analytics')->log('DEBUG', __METHOD__, 'Starting Batch Push Job');
            $callback = $this->makeCallback();
            $batchData = Mage::getModel('jirafe_analytics/data');
            $maxTime = $this->start + $this->getDuration();
            $historicalTime = $this->start + ($this->getDuration() / 2);

            // Get historical data for half of the alloted time.
            $this->resetTimer($historicalTime);
            $batchData->convertEventDataToBatchData(null, true, $callback);

            // Assume the worst, and that some some operation caused the complete time to be up.
            if (!$this->isTimeUp()) {
                // Run current for the remaining time.
                $this->resetTimer($maxTime);
                $batchData->convertEventDataToBatchData(null, false, $callback);
            }
            Mage::helper('jirafe_analytics')->log('DEBUG', __METHOD__, 'Stopping Batch Push Job');
        }
        catch (Exception $e)
        {
             Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
        }
    }
}

