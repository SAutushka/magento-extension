<?php

/**
 * Data Attempt Model
 *
 * Store cURL response information for every attempt at sending data to Jirafe API
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */
class Jirafe_Analytics_Model_Data_Attempt extends Jirafe_Analytics_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('jirafe_analytics/data_attempt');
    }

    protected function _getResponse($response)
    {
        $response = array();
        foreach(json_decode($response, true) as $value) {
            $response = array_merge($response, $value);
        }
        return $response;
    }

    protected function _createErrorRecord($data)
    {
        $data['errors'] = isset($data['errors']) ? json_encode($data['errors']) : null;
        $data['error_type'] = isset($data['error_type']) ? $data['error_type'] : null;
        $error = Mage::getModel('jirafe_analytics/data_error');
        return $error;
    }

    protected function _createAttemptRecord($created, $id)
    {
        $attempt = new $this;
        $attempt->setDataId($id);
        $attempt->setCreatedDt($created);
        $attempt->save();
        return $attempt;
    }

    /*
     * Update data record with success or failure.
     */
    protected function _updateDataRecord($id, $success, $created)
    {
        $element = Mage::getModel('jirafe_analytics/data')->load($id);
        $element->setAttemptCount(intval($element->getAttemptCount()) + 1);
        $element->setSuccess($success ? 1 : 0);
        $element->setCompletedDt($success ? $created : null);
        $element->save();
    }

    /*
     * Updates data record with failures.
     *
     * Used when a response cannot be decoded.
     */
    protected function _processError($created, $batch)
    {
        foreach ($batch as $_ => $data) {
            if (!array_key_exists('data_id', $data)) {
                Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, 'Batch has no data_id: skiping.');
                continue;
            }

            $id = $data['data_id'];
            $attempt = $this->_createAttemptRecord($created, $id);
            $this->_updateDataRecord($id, false, $created)
        }
        return true;
    }

    protected function _processSuccess($created, $batch, $response)
    {
        foreach ($batch as $pos => $data) {
            // Append response and attempt to data object
            if(is_array($response[$pos])) {
                $data = array_merge($data, $response[$pos]);
            }

            if (!array_key_exists('data_id', $data)) {
                Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, 'Batch has no data_id: skiping.');
                continue;
            }

            $id = $data['data_id'];
            $success = isset($data['success']) ? $data['success'] : false;
            $attempt = $this->_createAttemptRecord($created, $data);
            $this->_updateDataRecord($id, $success, $created)

                if (!$success) {
                    $error = $this->_createErrorRecord($data);
                    $error->add($data, $attempt->getId());
                }
        }
        return true;
    }

    /**
     * Store data for each API data attempt
     *
     * @param array $attempt    cURL reponse data for single API attempt
     * @return boolean
     * @throws Exception if unable to save attempt to db
     */
    public function add($attempt=null)
    {
        try {
            if (!$attempt) {
                Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, 'Empty attempt record: aborting.');
                return false;
            }

            if (!array_key_exists('batch_id', $attempt)) {
                Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, 'No batch id in attempt record: aborting.');
                return false;
            }

            /**
             * Get data ids associated with batch
             */
            $batch = Mage::getModel('jirafe_analytics/batch_data')
                ->getCollection()
                ->addFieldToFilter('batch_id',array('eq',$attempt['batch_id']))
                ->load()
                ->getData();


            /**
             * Separate API responses in order of batch items
             */
            $response = array();
            if (isset($attempt['response'])) {
                $_jsonDecode = json_decode($attempt['response'],true);
                if ($_jsonDecode) {
                    foreach($_jsonDecode as $key => $value) {
                        $response = array_merge($response, $value);
                    }
                } else {
                    Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, 'Invalid response: assuming 500.');
                    return $this->_processError($batch);
                }
            } else {
                Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, 'No response: assuming 500.');
                return $this->_processError($batch);
            }

            /**
             *  Counter var to tie order of data in response json to batch_order
             */
            $pos = 0;

            foreach ($batch as $data) {

                /**
                 * Append response and attempt to data object
                 */
                if(is_array($response[$pos]))
                {
                    $data = array_merge( $data,$response[$pos] );
                }

                $data['success'] = isset($data['success']) ? $data['success'] : false;
                $data['created_dt'] = $attempt['created_dt'];

                /**
                 *  Create record of attempt
                 */
                $obj = new $this;
                $obj->setDataId( $data['data_id'] );
                $obj->setCreatedDt( $data['created_dt'] );
                $obj->save();

                /**
                 *   Update data element with success or failure
                 */
                $element = Mage::getModel('jirafe_analytics/data')->load( $data['data_id'] );
                $element->setAttemptCount( intval( $element->getAttemptCount() ) + 1);
                $element->setSuccess( $data['success'] ? 1 : 0 );
                $element->setCompletedDt( $data['success'] ? $data['created_dt'] : null);
                $element->save();
            }
            return $this->_processSuccess($batch, $response);
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
    }
}
