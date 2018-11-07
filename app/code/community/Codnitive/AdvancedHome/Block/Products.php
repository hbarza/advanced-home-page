<?php
/**
 * CODNITIVE
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE_EULA.html.
 * It is also available through the world-wide-web at this URL:
 * http://www.codnitive.com/en/terms-of-service-softwares/
 * http://www.codnitive.com/fa/terms-of-service-softwares/
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade to newer
 * versions in the future.
 *
 * @category   Codnitive
 * @package    Codnitive_AdvancedHome
 * @author     Hassan Barza <support@codnitive.com>
 * @copyright  Copyright (c) 2012 CODNITIVE Co. (http://www.codnitive.com)
 * @license    http://www.codnitive.com/en/terms-of-service-softwares/ End User License Agreement (EULA 1.0)
 */

/**
 * Product options text type block
 *
 * @category   Codnitive
 * @package    Codnitive_AdvancedHome
 * @author     Hassan Barza <support@codnitive.com>
 */
class Codnitive_AdvancedHome_Block_Products extends Mage_Catalog_Block_Product_Abstract
{

    const DEFAULT_PRODUCTS_COUNT = 5;
    
    protected $_columnCount = 4;
    protected $_newProductsCount = null;
    protected $_blockClass;
    
    public function _construct()
    {
        parent::_construct();
        
        $categoryId = intval($this->getCategoryId());
        $collectionType = (string)$this->getCollectionType();
        $collectionLimit = intval($this->getCollectionLimit());
        $isAuto = (bool)$this->getIsAuto();
        $collectionColumnCount = intval($this->getCollectionColumnCount());
        $orderType = $this->getOrderType();
        
        if ($collectionColumnCount) {
            $this->setColumnCount($collectionColumnCount);
        }
        if ('new' === $collectionType) {
            $this->initNewProducts($categoryId, $collectionLimit, $orderType);
            $this->_blockClass = 'newprods';
        }
        if ('best' === $collectionType) {
            $this->initBestsellers($categoryId, $collectionLimit, $isAuto, $orderType);
            $this->_blockClass = 'bestsellers';
        }

        $page  = Mage::app()->getRequest()->getParam('p');
        $limit = Mage::app()->getRequest()->getParam('limit');

        $this->addData(array(
            'cache_lifetime'    => 86400,
            'cache_tags'        => array('advancedhome_products_' . $categoryId . '_' . $collectionType . '_' . $collectionLimit . '_' . $collectionColumnCount . '_' . $page . '_' . $limit),
            'cache_key'         => 'advancedhome_products_' . $categoryId . '_' . $collectionType . '_' . $collectionLimit . '_' . $collectionColumnCount . '_' . $page . '_' . $limit,
        ));
    }
    
    public function initBestsellers($categoryId = null, $limit = null, $isAuto = null, $orderType = 'random')
    {
        $categoryId = null === $categoryId ? 3 : $categoryId;
        $productLimit = null === $limit ? 15 : $limit;
        $auto = (bool) $isAuto;
        $orderType = 'new' === $orderType ? 'entity_id' : 'rand()';
        
        if (!$auto) {
            $collection = $this->_getFromCategory($categoryId, $productLimit, $orderType);
            if ((count($collection) > 0)) {
                $this->setBestsellersCollection($collection);
                return $this;
            }
            $auto = 1;
        }
        
        if ($auto == 1) {
            if (Mage::getStoreConfig('catalog/frontend/flat_catalog_product')) {
                $auto = 0;
            }
            else {
                $storeId = Mage::app()->getStore()->getId();

                $products = Mage::getResourceModel('reports/product_collection')
                        ->addOrderedQty()
                        ->addAttributeToSelect('*')
                        ->setStoreId($storeId)
                        ->addStoreFilter($storeId)
                        ->setOrder('ordered_qty', 'desc');

                Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($products);
                Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($products);
                Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($products);

                $products->setPageSize($productLimit)->setCurPage(1);

                $items = $products->getItems();
                (!$items || empty($items) || (count($items) <= 0)) ? $auto = 0 : $this->setBestsellersCollection($products);
            }
        }
        
        if (!$auto) {
            $storeId = Mage::app()->getStore()->getId();
            $product = Mage::getModel('catalog/product');
            
            $products = $product->setStoreId($storeId)->getCollection()
                    ->addAttributeToSelect('status')
                    ->addAttributeToFilter('bestsellers', array('Yes' => true))
                    ->addAttributeToSelect(array('name', 'price', 'small_image'), 'inner')
                    ->addAttributeToSelect(array('special_price', 'special_from_date', 'special_to_date'), 'left');
            
            Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($products);
            Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($products);
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($products);
            $products->getSelect()->order($orderType);
            $products->setOrder('hot_deals')->setPageSize($productLimit)->setCurPage(1);

            if (!$products || empty($products) || (count($products) <= 0)) {
                $products = $this->_initRandomProducts($productLimit);
            }
            
            $this->setBestsellersCollection($products);
        }
        
        return $this;
    }

    public function initNewProducts($categoryId = null, $limit = null, $orderType = 'random')
    {
        $categoryId = null === $categoryId ? 3 : $categoryId;
        $limit = null === $limit ? 15 : $limit;
        
        $collection = $this->_getFromCategory($categoryId, $limit, $orderType);
        if ((count($collection) > 0)) {
            $this->setNewProductCollection($collection);
            return $this;
        }
        
        $todayStartOfDayDate  = Mage::app()->getLocale()->date()
            ->setTime('00:00:00')
            ->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);

        $todayEndOfDayDate  = Mage::app()->getLocale()->date()
            ->setTime('23:59:59')
            ->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);

        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->setVisibility(Mage::getSingleton('catalog/product_visibility')->getVisibleInCatalogIds());

        $this->setNewProductsCount(15);
        $collection = $this->_addProductAttributesAndPrices($collection)
            ->addStoreFilter()
            ->addAttributeToFilter('news_from_date', array('or'=> array(
                0 => array('date' => true, 'to' => $todayEndOfDayDate),
                1 => array('is' => new Zend_Db_Expr('null')))
            ), 'left')
            ->addAttributeToFilter('news_to_date', array('or'=> array(
                0 => array('date' => true, 'from' => $todayStartOfDayDate),
                1 => array('is' => new Zend_Db_Expr('null')))
            ), 'left');
        
        $justNewFlagged = false;
        if ($justNewFlagged) {
            $collection->addAttributeToFilter(
                array(
                    array('attribute' => 'news_from_date', 'is'=>new Zend_Db_Expr('not null')),
                    array('attribute' => 'news_to_date', 'is'=>new Zend_Db_Expr('not null'))
                    )
              );
        }
        Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
        $collection->addAttributeToSort('news_from_date', 'desc')
            ->addAttributeToSort('created_at', 'desc')
            ->setPageSize($this->getNewProductsCount())
            ->setCurPage(1);

        $products = $collection->getItems();
        if (!$products || empty($products) || (count($products) <= 0)) {
            $collection = $this->_initRandomProducts($this->getNewProductsCount());
        }
        
        $this->setNewProductCollection($collection);
        
        return $this;
    }
    
    protected function _getFromCategory($categoryId, $productLimit = 10, $orderType = 'random')
    {
        $orderType = 'new' === $orderType ? 'entity_id' : 'rand()';
        $catagoryModel = Mage::getModel('catalog/category')->load($categoryId);
        $collection = Mage::getResourceModel('catalog/product_collection');
        
        $collection->addCategoryFilter($catagoryModel);
        $collection->addAttributeToSelect('status')
                ->addAttributeToSelect(array('name', 'price', 'small_image'), 'inner')
                ->addAttributeToSelect(array('special_price', 'special_from_date', 'special_to_date'), 'left');
        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($collection);
        Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
        
        $collection->getSelect()->order($orderType);
        $collection->addStoreFilter()->setPageSize($productLimit)->setCurPage(1);
        
        return $collection;
    }
    
    protected function _initRandomProducts($number)
    {
        $collection = Mage::getResourceModel('catalog/product_collection');
        Mage::getModel('catalog/layer')->prepareProductCollection($collection);
        
        $collection->addStoreFilter();
        Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
        $collection->getSelect()->order('rand()');
        $numProducts = $this->getNumProducts() ? $this->getNumProducts() : $number;
        $collection->setPage(1, $numProducts);
        
        return $collection;
    }

    public function setNewProductsCount($count)
    {
        $this->_newProductsCount = $count;
        return $this;
    }

    public function getNewProductsCount()
    {
        if (null === $this->_newProductsCount) {
            $this->_newProductsCount = self::DEFAULT_PRODUCTS_COUNT;
        }
        return $this->_newProductsCount;
    }
    
    public function setColumnCount($count)
    {
        $this->_columnCount = intval($count);
        return $this;
    }
    
    public function getColumnCount()
    {
        return $this->_columnCount;
    }
    
    public function getBlockClass()
    {
        return $this->_blockClass;
    }
    
}
