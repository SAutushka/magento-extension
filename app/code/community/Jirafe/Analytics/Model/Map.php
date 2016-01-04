<?php

/**
 * Map Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

class Jirafe_Analytics_Model_Map extends Jirafe_Analytics_Model_Abstract
{
    protected $_magentoFields = null;
    
    const VALID_FIELD_TYPES =  '|string|int|float|boolean|';
    
    /**
     * Class construction & resource initialization
     */
    
    protected function _construct()
    {
        $this->_init('jirafe_analytics/map');
    }
    
    /**
     * Return array of field mappings
     *
     * @return array
     * @throws Exception if unable to return array of field mappings from db
     */
    
    public function getArray() 
    {
        try {
           $map = array();
           $collection = $this->getCollection()->setOrder( 'element', 'ASC' );
           $last = null;
           $group = array();
           
           foreach ( $collection as $field ) {
               $current = $field->getElement();
               
               if (!$last) {
                   $last = $current;
               } else if ( $last != $current ) {
                   $map[ $last ] = $group;
                   $last = $current;
                   $group = array();
               }
               
               $group[ $field->getKey() ] =  array(
                   'element' => $field->getElement(),
                   'key' => $field->getKey(),
                   'api' => $field->getApi(), 
                   'magento' => $field->getMagento(), 
                   'type' => $field->getType(),
                   'default' => $field->getDefault() ) ;
              
               $last = $current;
           }
           
           $map[ $last ] = $group;
           
           return $map;
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::getArray()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get Magento cart fields available for mapping
     *
     * @return array
     * @throws Exception if unable to return array of fields
     */
    
    protected function _getCartFields(  $quote = null ) 
    {
        try {
            $output = array();
            
            if (!$quote) {
                $quote = Mage::getModel('sales/quote')->getCollection()->getFirstItem();
            }
            
            $output= $this->_flattenArray( array_keys( $quote->getData() ) );
            
            return $output;
            
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::_getCartFields()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get Magento cart item fields available for mapping
     *
     * @return array
     * @throws Exception if unable to return array of fields
     */
    
    protected function _getCartItemFields( $quote = null )
    {
        try {
            $output = array();
            
            if (!$quote) {
                $quote = Mage::getModel('sales/quote')->getCollection()->getFirstItem();
            }
            
            if ( $quoteItem = $quote->getItemsCollection()->getData() ) {
                $output = $this->_flattenArray( array_keys( $quoteItem[0] ) );
            }
            
            return $output;
            
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::_getCartItemFields()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get Magento category fields available for mapping
     *
     * @return array
     * @throws Exception if unable to return array of fields
     */
    
    protected function _getCategoryFields()
    {
        try {
            $output = array();
            
            if ( $category = Mage::getModel('catalog/category')->getCollection()->getFirstItem()->getData() ) {
                $output = $this->_flattenArray( array_keys( $category ) );
            }
            
            return $output;
            
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::_getCategoryFields()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get Magento customer fields available for mapping
     *
     * @return array
     * @throws Exception if unable to return array of fields
     */
    
    protected function _getCustomerFields()
    {
        try {
            $output = array();
            
            if ( $customer = Mage::getModel('customer/customer')->getCollection()->getFirstItem()->getData() ) {
                $output = $this->_flattenArray( array_keys( $customer ) );
            }
            
            return $output;
            
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::_getCustomerFields()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get Magento employee fields available for mapping
     *
     * @return array
     * @throws Exception if unable to return array of fields
     */
    
    protected function _getEmployeeFields()
    {
        try {
            $output = array();
            
            if ( $employee = Mage::getModel('admin/user')->getCollection()->getFirstItem()->getData() ) {
                $output = $this->_flattenArray( array_keys( $employee ) );
            }
            
            return $output;
            
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::_getEmployeeFields()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get Magento order fields available for mapping
     *
     * @return array
     * @throws Exception if unable to return array of fields
     */
    
    protected function _getOrderFields()
    {
        try {
            $output = array();
            
            if ( $order = Mage::getModel('sales/order')->getCollection()->getFirstItem()->getData() ) {
                $output = $this->_flattenArray( array_keys( $order ) );
            }
            
            return $output;
            
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::_getOrderFields()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get Magento order item fields available for mapping
     *
     * @return array
     * @throws Exception if unable to return array of fields
     */
    
    protected function _getOrderItemFields()
    {
        try {
            $output = array();
            
                if ( $order_item = Mage::getModel('sales/order_item')->getCollection()->getFirstItem()->getData() ) {
                    $output = $this->_flattenArray( array_keys( $order_item ) );
                }
            
            return $output;
            
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::_getOrderItemFields()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get Magento product fields available for mapping
     *
     * @return array
     * @throws Exception if unable to return array of fields
     */
    
    protected function _getProductFields()
    {
        try {
            $output = array();
            
            
            if ( $product = Mage::getModel('catalog/product')->getCollection()->getFirstItem()->getData() ) {
                $output = $this->_flattenArray( array_keys( $product ) );
            }
            
            $collection = Mage::getResourceModel('catalog/product_attribute_collection');
            $attributes = array();
            
            foreach ($collection as $attribute) {
                $code = $attribute->getAttributeCode();
                if ( !array_search($code,$output) ) {
                    $attributes[] = $code;
                }
            }
            
            if ($attributes) {
                $output = array_merge( $output, $this->_flattenArray($attributes) );
            }
            return $output;
            
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::_getProductFields()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get Magento fields by element name available for mapping
     *
     * @return mixed
     * @throws Exception if unable to return array of fields
     */
    
    protected function _getMagentoFieldsByElement( $element = null)
    {
        try {
            
            if ( $element ) {
                switch ( $element ) {
                    case 'cart':
                        return $this->_getCartFields();
                        break;
                    case 'cart_item':
                        return $this->_getCartItemFields();
                        break;
                    case 'category':
                        return $this->_getCategoryFields();
                        break;
                    case 'customer':
                        return $this->_getCustomerFields();
                        break;
                    case 'employee':
                        return $this->_getEmployeeFields();
                        break;
                    case 'order':
                        return $this->_getOrderFields();
                        break;
                    case 'order_item':
                        return $this->_getOrderItemFields();
                        break;
                    case 'product':
                        return $this->_getProductFields();
                        break;
                    default:
                        return null;
                        break;
                }
            }
            return null;
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::_getMagentoFieldsByElement()', $e->getMessage(), $e);
            return false;
        }
         
    }
    
    /**
     * validate Magento element identifer
     *
     * @return boolean
     * @throws Exception if unable to validate element identifier
     */
    
    public function validateElement( $element )
    {
        try {
        if ( $element ) {
                $count = $this->getCollection()
                        ->addFieldToFilter( 'element', $element )
                        ->getSize();
                
                return $count ? true : false;
            } else {
                return false;
            }
    
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::validateElement()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * validate key
     *
     * @return boolean
     * @throws Exception if unable to validate key
     */
    
    public function validateKey( $element, $key )
    {
        try {
            if ( $element && $key) {
                $count = $this->getCollection()
                ->addFieldToFilter( 'element', $element )
                ->addFieldToFilter( 'key', $key )
                ->getSize();
                
                return $count ? true : false;
            } else {
                return false;
            }
            
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::validateKey()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * validate Magento field name
     *
     * @return boolean
     * @throws Exception if unable to validate field name
     */
    
    public function validateField( $element, $field )
    {
        try {
            if ( $element && $field ) {
                $fields = $this->_getMagentoFieldsByElement( $element ); 
                return in_array( $field, $fields );
            } else {
                return false;
            }
        
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::validateField()', $e->getMessage(), $e);
            return false;
        }
    }
    
    /**
     * Get field map object to update
     *
     * @return Jirafe_Analytics_Model_Map
     * @throws Exception if unable to query db for field object
     */
    
    public function getMapByElementKey( $element, $key )
    {
        try {
            if ( $element && $key ) {
                return $this->getCollection()
                        ->addFieldToFilter( 'element', $element )
                        ->addFieldToFilter( 'key', $key )
                        ->getFirstItem();
            } else {
                return null;
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Map::getMapByElementKey()', $e->getMessage(), $e);
            return false;
        }
    }
    
}