<?php

/**
 * Category Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */
class Jirafe_Analytics_Model_Category extends Jirafe_Analytics_Model_Abstract implements Jirafe_Analytics_Model_Pagable
{

    /**
     * Create category array of data required by Jirafe API
     *
     * @param Mage_Catalog_Model_Category $category
     * @return mixed
     */

    public function getArray( $category = null )
    {
        try {
            if ($category) {

             /**
              * Get field map array
              */
             $fieldMap = $this->_getFieldMap( 'category', $category );

             $data = array(
                 $fieldMap['id']['api'] => $fieldMap['id']['magento'],
                 $fieldMap['name']['api'] => $fieldMap['name']['magento'],
                 $fieldMap['change_date']['api'] => $fieldMap['change_date']['magento'],
                 $fieldMap['create_date']['api'] => $fieldMap['create_date']['magento']
             );

             if ( $parent = Mage::getModel('catalog/category')->load($category->getParentId()) ) {
                 $fieldMap = $this->_getFieldMap( 'category', $parent );
                 $parent = array();
                 $parent[] = array(
                     $fieldMap['id']['api'] => $fieldMap['id']['magento']
                 );

                 $data['parent_categories'] = $parent;
             }

              return $data;
            } else {
                return array();
            }
        } catch (Exception $e) {
            Mage::helper('jirafe_analytics')->log('ERROR', 'Jirafe_Analytics_Model_Category::getArray()', $e->getMessage(), $e);
            return false;
        }
    }

    /**
     * Convert category array into JSON object
     *
     * @param array $category
     * @return mixed
     */

    public function getJson( $category = null )
    {
        if ($category) {
            return json_encode( $this->getArray( $category ) );
        } else {
            return false;
        }
    }

    public function getDataType() {
        return Jirafe_Analytics_Model_Data_Type::CATEGORY;
    }

   /**
     * Create array of category historical data
     *
     * @param string $filter
     * @return Zend_Paginator
     */
    public function getPaginator($websiteId, $lastId = null)
    {
        $storeIds = Mage::app()->getWebsite($websiteId)->getStoreIds();
        $categories = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('name')
            ->setOrder('entity_id');

        if ($lastId) {
            $categories->addAttributeToFilter('entity_id', array('gt' => $lastId));
        }

        $filteredCategories = array_filter(
            iterator_to_array($categories),
            function($category) use ($storeIds) {
                $inter = array_intersect($category->getStoreIds(), $storeIds);
                return !empty($inter);
            }
        );

        return Zend_Paginator::factory($filteredCategories);
    }
}

