<?php

/**
 * Cart Item Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */
class Jirafe_Analytics_Model_Cart_Item extends Jirafe_Analytics_Model_Cart
{
    /**
     * Create cart item array of data required by Jirafe API
     *
     * @param  string $quoteId
     * @param  string $storeId
     * @return mixed
     */
    public function getItems( $quoteId = null, $storeId = null, $currency = null )
    {
        try {
            if ($quoteId) {
                $columns = $this->_getAttributesToSelect( 'cart_item' );
                $columns[] = 'product_id';
                $columns[] = 'option.value as attributes';
                $columns[] = 'IF(main_table.row_total > 0, main_table.row_total, parent.row_total) AS row_total';
                $columns[] = 'IF(main_table.discount_amount > 0,main_table.discount_amount ,parent.discount_amount) AS discount_amount';

                $collection = Mage::getModel('sales/quote_item')
                    ->getCollection()
                    ->getSelect()
                    ->joinLeft(array('parent'=>Mage::getSingleton('core/resource')->getTableName('sales/quote_item')), "main_table.parent_item_id = parent.item_id", array('parent.row_total'))
                    ->joinLeft(array('option'=>Mage::getSingleton('core/resource')->getTableName('sales/quote_item_option')), "parent.item_id = option.item_id AND option.code = 'attributes'", array('option.value'))
                    ->reset(Zend_Db_Select::COLUMNS)
                    ->columns($columns)
                    ->where("main_table.quote_id = ?", $quoteId)
                    ->where("main_table.product_type != ? AND (parent.product_type != 'bundle' OR parent.product_type is null)", Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE)
                    ->distinct(true);

                $count = 1;
                $data = array();
                $helper = Mage::helper('jirafe_analytics');

                foreach($collection->query() as $item) {
                    $price = floatval($item['row_total']);
                    $discount_price = floatval($item['discount_amount']);
                    $fieldMap = $this->_getFieldMap( 'cart_item', $item );

                    $itemData = array(
                        $fieldMap['id']['api'] => $fieldMap['id']['magento'],
                        $fieldMap['create_date']['api'] => $fieldMap['create_date']['magento'],
                        $fieldMap['change_date']['api'] => $fieldMap['change_date']['magento'],
                        'cart_item_number' => "$count",
                        'quantity' => intval( $item['qty'] ),
                        'price' => $price,
                        'discount_price' => $discount_price,
                        'product' => Mage::getModel('jirafe_analytics/product')->getArray(Mage::getModel('catalog/product')->load($item['product_id']), $item['attributes'])
                    );
                    try {
                        if ($helper->shouldConvertCurrency($currency)) {
                            $itemData['price'] = $helper->convertCurrency($price, $currency);
                            $itemData['discount_price'] = $helper->convertCurrency($price, $currency);
                        }
                    } catch (Exception $e) {
                        Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, $e->getMessage(), $e);
                        Mage::helper('jirafe_analytics')->log('ERROR', __METHOD__, "Error converting currency: $currency");
                    }
                    $data[] = $itemData;
                    $count++;
                }
                return $data;
            } else {
                return array();
            }
        } catch (Exception $e) {
            Mage::log('ERROR Jirafe_Analytics_Model_Cart_Item::getItems(): ' . $e->getMessage(),null,'jirafe_analytics.log');
            return false;
        }
    }
}
