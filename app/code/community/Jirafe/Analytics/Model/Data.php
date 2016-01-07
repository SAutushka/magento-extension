<?php

/**
 * Data Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 *
 */

class Jirafe_Analytics_Model_Data extends Jirafe_Analytics_Model_Abstract
{

    protected $paginators = array();
    protected $_maxAttempts = null;

    /**
     * Class construction & resource initialization
     */

    protected function _construct()
    {
        $this->_init('jirafe_analytics/data');
        $this->maxAttempts = intval(Mage::getStoreConfig('jirafe_analytics/curl/max_attempts'));
    }

    public function setWebsiteId($websiteId)
    {
        $this->setStoreId($websiteId);
    }

    /**
     * Return all website ids from unbatched data
     *
     * @return array
     * @throws Exception if unable to return website ids
     */
    protected function _getWebsites()
    {
        try {
            // NOTE: store_id is really website_id on the jirafe_analytics_data table
            return Mage::getModel('core/website')->getCollection()
                ->addFieldToSelect(array('website_id'))
                ->getSelect()
                ->join( array('d'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/data')), "main_table.website_id = d.store_id", array())
                ->joinLeft( array('bd'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/batch_data')), "d.id = bd.data_id", array())
                ->where('d.completed_dt is NULL')
                ->distinct(true)
                ->query();

        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
            return false;
        }
    }

    /**
     * Return all types from website with unbatched data
     *
     * @param string $websiteId
     * @return array
     * @throws Exception if unable to return data types
     */
    protected function _getTypesForWebsite($websiteId = null)
    {
        try {
            if (is_numeric($websiteId)) {
                // NOTE: store_id is really website_id on the jirafe_analytics_data_type table
                return Mage::getModel('jirafe_analytics/data_type')
                    ->getCollection()
                    ->addFieldToSelect(array('type'))
                    ->getSelect()
                    ->join( array('d'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/data')), "main_table.id = d.type_id AND d.json is not null AND d.store_id = $websiteId",array())
                    ->where('d.completed_dt is NULL')
                    ->where('d.attempt_count < ?', $this->maxAttempts)
                    ->distinct(true)
                    ->query();
            } else {
                return array();
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
            return false;
        }
    }

    /**
     * Return data ready to be batched
     *
     * @param string $websiteId
     * @param string $typeId
     * @return array
     * @throws Exception if unable to return data types
     */
    protected function _getItems($websiteId = null, $typeId = null, $historical)
    {
        try {
            if (is_numeric($websiteId) && is_numeric($typeId)) {
                // NOTE: store_id is really website_id on the jirafe_analytics_data table
                $query = Mage::getModel('jirafe_analytics/data')
                    ->getCollection()
                    ->addFieldToSelect(array('json'))
                    ->addFieldToFilter('main_table.json', array('neq' => ''))
                    ->addFieldToFilter('main_table.store_id', array('eq' => $websiteId))
                    ->addFieldToFilter('main_table.attempt_count', array('lt' => $this->maxAttempts));
                if(!$historical) {
                    $query = $query->addFieldToFilter('main_table.historical', array('neq' => '1'));
                } else {
                    $query = $query->addFieldToFilter('main_table.historical', array('eq' => '1'));
                }
                $query = $query->getSelect()
                    ->join( array('dt'=>Mage::getSingleton('core/resource')->getTableName('jirafe_analytics/data_type')), "main_table.type_id = dt.id AND dt.id = $typeId",array('dt.type'))
                    ->where('main_table.completed_dt is NULL')
                    ->columns('main_table.store_id as website_id')
                    ->query();

                return $query;
            } else {
                return array();
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
            return false;
        }
    }

    /**
     * Convert event data to batch
     *
     * @return boolean
     * @throws Exception if create batch array fails
     */
    public function convertEventDataToBatchData($params=null, $historical=false, $abortAfterBatchSave = null)
    {
        try {
            Mage::helper('jirafe_analytics')->overridePhpSettings($params);
            // `maxSize` is in bytes.
            if (isset($params['json_max_size'])) {
                $maxSize = intval( $params['json_max_size'] );
            } else {
                $maxSize = Mage::getStoreConfig('jirafe_analytics/curl/max_size');
            }

            // Separate data by website id since each website has a separate site_id and oauth token
            foreach ($this->_getWebsites() as $website) {
                $enabled = Mage::app()->getWebsite($website['website_id'])->getConfig('jirafe_analytics/general/enabled');
                if ($enabled != 1) {
                    // not a valid site id
                    continue;
                }

                // Initialize batch database object and array container
                Mage::helper('jirafe_analytics')->log(
                    'DEBUG', __METHOD__,
                    sprintf('Website Id: %d | Convert events into batches', $website['website_id'])
                );

                $batch = Mage::getModel('jirafe_analytics/batch');

                foreach ($this->_getTypesForWebsite($website['website_id']) as $type) {
                    $items = iterator_to_array($this->_getItems($website['website_id'], $type['id'], $historical));
                    Mage::helper('jirafe_analytics')->log(
                        'DEBUG', __METHOD__,
                        sprintf('website Id: %d | Batching %d items of type %s', $website['website_id'], count($items), $type['type'])
                    );

                    foreach ($items as $item) {
                        if (!$batch->addItem($item, $type['type'], $maxSize)) {
                            // it's full, save it
                            $this->_saveBatch($batch, $website['website_id'], $historical);
                            if (is_callable($abortAfterBatchSave)) {
                                if ($abortAfterBatchSave($batch)) {
                                    return true;
                                }
                            }

                            // now, start a new one
                            $batch = Mage::getModel('jirafe_analytics/batch');
                            // add the item that didn't make into th eold one, to the new one
                            $batch->addItem($item, $type['type'], $maxSize);
                        }
                    }
                }

                // if there's anything left in the batch, flush it, because we're at the end of this website
                $this->_saveBatch($batch, $website['website_id'], $historical);
                if (is_callable($abortAfterBatchSave)) {
                    if ($abortAfterBatchSave($batch)) {
                        return true;
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
            return false;
        }
    }

    /**
     * Save data to batch
     *
     * @return boolean
     * @throws Exception if failure to save batch
     */
    protected function _saveBatch($batch = null, $websiteId = null, $historical = false)
    {
        try {
            if ($batch && $batch->hasRawData() && is_numeric($websiteId)) {
                $batch->setStoreId($websiteId);
                $batch->setCreatedDt(Mage::helper('jirafe_analytics')->getCurrentDt());

                if ($historical) {
                    $batch->setHistorical(1);
                }
                $batch->save();
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
            return false;
        }
    }

    /**
     * Purge data
     *
     * @return boolean
     * @throws Exception if failure to purge data
     */
    public function purgeData()
    {
        try {
            $minutes = Mage::getStoreConfig('jirafe_analytics/general/purge_time');
            if (intval($minutes) > 15) {
                $resource = Mage::getSingleton('core/resource');

                $result = $resource->getConnection('core_write')->query( sprintf("DELETE FROM %s WHERE id IN (SELECT data_id FROM %s) AND TIMESTAMPDIFF(MINUTE,captured_dt,'%s') > %d",
                    $resource->getTableName('jirafe_analytics/data'),
                    $resource->getTableName('jirafe_analytics/batch_data'),
                    Mage::helper('jirafe_analytics')->getCurrentDt(),
                    $minutes)
                );

                $result = $resource->getConnection('core_write')->query( sprintf("DELETE FROM %s WHERE TIMESTAMPDIFF(MINUTE,completed_dt,'%s') > %d",
                    $resource->getTableName('jirafe_analytics/batch'),
                    Mage::helper('jirafe_analytics')->getCurrentDt(),
                    $minutes)
                );

                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
            return false;
        }
    }

    public function saveHistoricalData($paginator, $type)
    {
        if ($paginator) {
            foreach ($paginator as $item) {
                $data = Mage::getModel('jirafe_analytics/data');
                $data->setStoreId($item['website_id']);
                $data->setTypeId($item['type_id']);
                $data->setHistorical(1);
                $data->setJson($item['json']);
                $data->setCapturedDt( Mage::helper('jirafe_analytics')->getCurrentDt());
                $data->save();
            }
            Mage::helper('jirafe_analytics')->log('DEBUG', __METHOD__, count($paginator) . ' ' . $type . ' historical records saved.');
        } else {
            Mage::helper('jirafe_analytics')->log('DEBUG', __METHOD__, 'No Historical Data for ' . $type);
        }
    }

    protected function getPaginator($websiteId, $datatype, $model, $lastId)
    {
        if (!array_key_exists($websiteId, $this->paginators)) {
            $this->paginators[$websiteId] = array();
        }

        if (!array_key_exists($datatype, $this->paginators[$websiteId])) {
            $paginator = $model->getPaginator($websiteId, $lastId);
            $this->paginators[$websiteId][$datatype] = $paginator;

            if ($paginator->count() == 0) {
                return null;
            } else {
                $page_size = Mage::app()->getWebsite($websiteId)->getConfig('jirafe_analytics/historicalpull/page_size', $websiteId);
                if (is_numeric($page_size)) {
                    $paginator->setItemCountPerPage((int)$page_size);
                }
            }
        } else {
            $paginator = $this->paginators[$websiteId][$datatype];
            if ($paginator->count() == 0 || $paginator->getCurrentPageNumber() == $paginator->count()) {
                return null;
            }
            $paginator->setCurrentPageNumber($paginator->getCurrentPageNumber() + 1);
        }
        return $paginator;
    }

    /**
     * Convert historical data into JSON
     *
     * @return array
     */
    public function convertHistoricalData($datatypes, $websiteId, $phpOverride=null)
    {
        try {
            Mage::helper('jirafe_analytics')->log(
                'DEBUG',
                __METHOD__,
                sprintf('Converting historical data for: "%s" on website: "%s"', join(",", $datatypes), $websiteId)
            );

            Mage::helper('jirafe_analytics')->overridePhpSettings($phpOverride);
            $moreData = false;
            foreach ($datatypes as $datatype) {
                try {
                    $lastIdPath = 'jirafe_analytics/last_id/' . $datatype;
                    $model      = Mage::getModel('jirafe_analytics/' . $datatype);
                    $lastId     = Mage::app()->getWebsite($websiteId)->getConfig($lastIdPath);
                    $paginator  = $this->getPaginator($websiteId, $datatype, $model, $lastId);

                    Mage::helper('jirafe_analytics')->log(
                        'DEBUG', __METHOD__,
                        sprintf('(Model, WebsiteId, LastId) : (%s, %s, %s)', $datatype, $websiteId, $lastId)
                    );

                    if (!$paginator) {
                        Mage::helper('jirafe_analytics')->log(
                            'DEBUG', __METHOD__,
                            sprintf('No more pages for model %s on webite %s', $datatype, $websiteId)
                        );
                        continue;
                    } else {
                        Mage::helper('jirafe_analytics')->log(
                            'DEBUG', __METHOD__,
                            sprintf('Page %s/%d for model %s on website %s', $paginator->getCurrentPageNumber(), $paginator->count(), $datatype, $websiteId)
                        );
                    }

                    list($newLastId, $historicalData) = $model->getHistoricalData($paginator, $websiteId);

                    if (!empty($historicalData)) {
                        $this->saveHistoricalData($historicalData, $datatype);
                    }

                    // Update last page id
                    if ($newLastId !== null) {
                        Mage::getConfig()->saveConfig($lastIdPath, $newLastId, 'websites', $websiteId);
                        Mage::getConfig()->reinit();
                    }

                    if ($paginator->getCurrentPageNumber() < $paginator->count()) {
                        $moreData = true;
                    }
                } catch(Exception $e) {
                    Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
                }
            }
            return $moreData;
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
            return true;
        }
    }
}

