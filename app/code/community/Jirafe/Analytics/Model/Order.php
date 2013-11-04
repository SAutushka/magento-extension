<?php

/**
 * Order Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Model_Order extends Jirafe_Analytics_Model_Abstract
{
   
    /**
     * Create order array of data required by Jirafe API
     *
     * @param array $order
     * @return mixed
     */
    
    public function getArray( $order = null, $items = null, $payment = null )
    {
        try {
            
            $status = $this->_mapOrderStatus( $order['status'] );
            /**
             * Get field map array
             */
            
            $fieldMap = $this->_getFieldMap( 'order', $order );
            
            if ($status == 'cancelled') {
                
                $data = array(
                    $fieldMap['order_number']['api'] => $order['increment_id'],
                    'status' => $status,
                    $fieldMap['cancel_date']['api'] => $this->_formatDate( $order['updated_at'] )
                );
                
            } else {
                
                $order['payment'] = Mage::getModel('jirafe_analytics/order_payment')->getPayment( $order['entity_id'] );
                
                $items = Mage::getModel('jirafe_analytics/order_item')->getItems( $order['entity_id'] );
                
                $data = array(
                    $fieldMap['order_number']['api'] => $fieldMap['order_number']['magento'],
                    $fieldMap['cart_id']['api'] => $fieldMap['cart_id']['magento'],
                    'status' => $status,
                    $fieldMap['order_date']['api'] => $fieldMap['order_date']['magento'],
                    $fieldMap['create_date']['api'] => $fieldMap['create_date']['magento'],
                    $fieldMap['change_date']['api'] =>$fieldMap['change_date']['magento'],
                    $fieldMap['subtotal']['api'] => $fieldMap['subtotal']['magento'],
                    $fieldMap['total']['api'] => $fieldMap['total']['magento'],
                    $fieldMap['total_tax']['api'] => $fieldMap['total_tax']['magento'],
                    $fieldMap['total_shipping']['api'] => $fieldMap['total_shipping']['magento'],
                    $fieldMap['total_payment_cost']['api'] => $fieldMap['total_payment_cost']['magento'],
                    $fieldMap['total_discounts']['api'] => $fieldMap['total_discounts']['magento'],
                    $fieldMap['currency']['api'] => $fieldMap['currency']['magento'],
                    'cookies' => $this->_getCookies(),
                    'items' => $items,
                    'previous_items' => $this->_getPreviousItems( $order['entity_id'] ),
                    'customer' => $this->_getCustomer( $order ),
                    'visit' => $this->_getVisit()
                );
                
                Mage::getSingleton('core/session')->setJirafePrevOrderId( $order['entity_id'] );
                Mage::getSingleton('core/session')->setJirafePrevOrderItems( $items );
            }
            return $data;
            
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR Jirafe_Analytics_Model_Order::getOrder(): ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get items from previous instance of order from session
     *
     * @param int $quoteId
     * @return array
     */
    
    protected function _getPreviousItems ( $orderId = null )
    {
        try {
            if ($orderId == Mage::getSingleton('core/session')->getJirafePrevOrderId()) {
                return Mage::getSingleton('core/session')->getJirafePrevOrderItems();
            } else {
                return array();
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Order::_getPreviousItems()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Convert order array into JSON object
     *
     * @param  array $order
     * @return mixed
     */
    
    public function getJson( $order = null )
    {
        if ($order) {
            return json_encode( $this->getArray( $order ) );
        } else {
            return false;
        }
        
    }
    
    
    /**
     * Map Magento order status values to Jirafe API values
     *
     * @param  string $status
     * @return string
     */
    
    protected function _mapOrderStatus( $status )
    {
        switch ( $status ) {
            case 'pending':
                return 'placed';
                break;
            case 'canceled':
                return 'cancelled';
                break; 
            default:
                return $status;
                break;
        }
    }
    
    /**
     * Create array of product historical data
     *
     * @return array
     */
    
    public function getHistoricalData( $startDate = null, $endDate = null )
    {
        try {
            
            $columns = $this->_getAttributesToSelect( 'order' );
            $columns[] = 'store_id';
            $columns[] = 'entity_id';
            $columns[] = 'status';
            $columns[] = 'customer_id';
            $orders = Mage::getModel('sales/order')->getCollection()->getSelect();
            $orders->reset(Zend_Db_Select::COLUMNS)->columns( $columns );
            
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
                $orders->where( $where );
            }
            
            $data = array();
            
            foreach($orders->query() as $order) {
                
                $data[] = array(
                    'type_id' => Jirafe_Analytics_Model_Data_Type::ORDER,
                    'store_id' => $order['store_id'],
                    'json' => $this->getJson( $order )
                );
                
            }
           
            return $data;
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Order::getHistoricalData()', $e->getMessage(), $e);
            return false;
        }
    }
}