<?php

/**
 * Cart Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Model_Cart extends Jirafe_Analytics_Model_Abstract
{
    
    /**
     * Create cart array of data required by Jirafe API
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return mixed
     */
    
    public function getArray( $quote = null ) 
    {
        try {
            if ($quote) {
                
                $items = Mage::getModel('jirafe_analytics/cart_item')->getItems( $quote['entity_id'] );
                /**
                 * Get field map array
                 */
                
                $fieldMap = $this->_getFieldMap( 'cart', $quote );
                
                $data = array(
                     $fieldMap['id']['api'] => $fieldMap['id']['magento'],
                     $fieldMap['create_date']['api'] => $fieldMap['create_date']['magento'],
                     $fieldMap['change_date']['api'] => $fieldMap['change_date']['magento'],
                     $fieldMap['subtotal']['api'] => $fieldMap['subtotal']['magento'],
                     $fieldMap['total']['api'] => $fieldMap['total']['magento'] ,
                     $fieldMap['total_tax']['api'] => $fieldMap['total_tax']['magento'],
                     $fieldMap['total_shipping']['api'] => $fieldMap['total_shipping']['magento'],
                     $fieldMap['total_payment_cost']['api'] => $fieldMap['total_payment_cost']['magento'],
                     $fieldMap['total_discounts']['api'] => $fieldMap['total_discounts']['magento'],
                     $fieldMap['currency']['api'] => $fieldMap['currency']['magento'],
                    'cookies' => (object) null,
                    'items' => $items,
                    'previous_items' => $this->_getPreviousItems( $quote['entity_id'] ),
                    'customer' => $this->_getCustomer( $quote ),
                    'visit' => $this->_getVisit()
                    );
                
                Mage::getSingleton('core/session')->setJirafePrevQuoteId( $quote['entity_id'] );
                Mage::getSingleton('core/session')->setJirafePrevQuoteItems( $items );
                
                return $data;
            } else {
                return false;
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log( 'ERROR', 'Jirafe_Analytics_Model_Cart::getArray()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get items from previous instance of cart from session
     * 
     * @param int $quoteId
     * @return mixed
     */
    
    protected function _getPreviousItems ( $quoteId = null )
    {
        try {
            if ($quoteId == Mage::getSingleton('core/session')->getJirafePrevQuoteId()) {
                return Mage::getSingleton('core/session')->getJirafePrevQuoteItems();
            } else {
                return array();
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log( 'ERROR', 'Jirafe_Analytics_Model_Cart::_getPreviousItems()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Convert cart array into JSON object
     *
     * @param  array $quote
     * @return mixed
     */
    
    public function getJson( $quote = null )
    {
        if ($quote) {
            return json_encode( $this->getArray($quote) );
        } else {
            return false;
        }
        
    }
    
    /**
     * Create array of cart historical data
     *
     * @return array
     */
    public function getHistoricalData( $startDate = null, $endDate = null )
    {
        try {
            
            $columns = $this->_getAttributesToSelect( 'cart' );
            $columns[] = 'store_id';
            
            $collection = Mage::getModel('sales/quote')->getCollection()->getSelect();
            $collection->reset(Zend_Db_Select::COLUMNS)->columns( $columns );
            
            if ( $startDate && $endDate ){
                $where = "created_at BETWEEN '$startDate' AND '$endDate'";
            } else if ( $startDate && !$endDate ){
                $where = "created_at >= '$startDate'";
            } else if ( !$startDate && $endDate ){
                $where = "created_at <= 'endDate'";
            } else {
                $where = null;
            }
            
            if ($where) {
                $collection->where( $where );
            }
            
            $data = array();
            
            foreach($collection->query() as $item) {
                $data[] = array( 
                       'type_id' => Jirafe_Analytics_Model_Data_Type::CART,
                       'store_id' => $item['store_id'],
                       'json' => $this->getJson( $item )
                   );
            }
            
            return $data;
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Cart::getHistoricalData()', $e->getMessage(), $e);
            return false;
        }
    }
    
}