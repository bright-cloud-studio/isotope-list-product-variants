<?php

/**
 * Bright Cloud Studio's Isotope List Ordered Products
 *
 * Copyright (C) 2023 Bright Cloud Studio
 *
 * @package    bright-cloud-studio/isotope-list-ordered-products
 * @link       https://www.brightcloudstudio.com/
 * @license    http://opensource.org/licenses/lgpl-3.0.html
**/

  
namespace Bcs\Module;

use Contao\Database;
use Contao\Date;
use Contao\Environment;

use Haste\Generator\RowClass;
use Haste\Http\Response\HtmlResponse;
use Haste\Input\Input;
use Haste\Util\Url;

use Isotope\Collection\ProductPrice as ProductPriceCollection;

use Isotope\Model\Product;
use Isotope\Model\ProductCache;
use Isotope\Model\ProductPrice;
use Isotope\Module\ProductList;
use Isotope\Model\ProductCollectionItem;
use Isotope\Model\ProductCollection\Order;
use Isotope\Interfaces\IsotopeProduct;




use Contao\FrontendUser;

class ListProductVariants extends ProductList
{
    // Template
    protected $strTemplate = 'mod_iso_productlist';
    
    public function generate()
    {
		//if($this->enableBatchAdd)
			//$this->strTemplate = 'mod_iso_list_ordered_products_batch';

        return parent::generate();
    }
    
    protected function compile()
    {

        global $objPage;
        $cacheKey      = $this->getCacheKey();
        $arrProducts   = array();
        $arrCacheIds   = null;
        
        
        // Get the parent alias, by subtracting the page alias from the environment request
        $alias = str_replace($objPage->alias . '/', "", Environment::get('request'));
        // Get the product that has this alias, as it is our parent product
        $parentProd = Product::findOneBy(['tl_iso_product.alias=?'],[$alias]);
        // Pass in our parent's ID to the custom find products function
        $tmp_prods = $this->findOrderedProducts($parentProd->id);
        
        
        // Success, at this point we have successfully found our stuffs
        foreach($tmp_prods as $prod) {
            $arrProducts[] = $prod;
        }

    
        if (!\is_array($arrProducts)) {
            // Display "loading products" message and add cache flag
            if ($this->blnCacheProducts) {
                $blnCacheMessage = (bool) ($this->iso_productcache[$cacheKey] ?? false);

                if ($blnCacheMessage && !Input::get('buildCache')) {
                    // Do not index or cache the page
                    $objPage->noSearch = 1;
                    $objPage->cache    = 0;

                    $this->Template          = new Template('mod_iso_productlist_caching');
                    $this->Template->message = $GLOBALS['TL_LANG']['MSC']['productcacheLoading'];

                    return;
                }

                // Start measuring how long it takes to load the products
                $start = microtime(true);

                // Load products
                $arrProducts = $this->findProducts($arrCacheIds);

                // Decide if we should show the "caching products" message the next time
                $end = microtime(true) - $start;
                $this->blnCacheProducts = $end > 1;

                $arrCacheMessage = $this->iso_productcache;
                if ($blnCacheMessage !== $this->blnCacheProducts) {
                    $arrCacheMessage[$cacheKey] = $this->blnCacheProducts;

                    $data = serialize($arrCacheMessage);

                    // Automatically clear iso_productcache if it exceeds the blob field length
                    if (strlen($data) > 65535) {
                        $data = serialize([$cacheKey => $this->blnCacheProducts]);
                    }

                    Database::getInstance()
                        ->prepare('UPDATE tl_module SET iso_productcache=? WHERE id=?')
                        ->execute($data, $this->id)
                    ;
                }

                // Do not write cache if table is locked. That's the case if another process is already writing cache
                if (ProductCache::isWritable()) {
                    Database::getInstance()
                        ->lockTables(array(ProductCache::getTable() => 'WRITE', 'tl_iso_product' => 'READ'))
                    ;

                    $arrIds = array();
                    foreach ($arrProducts as $objProduct) {
                        $arrIds[] = $objProduct->id;
                    }

                    // Delete existing cache if necessary
                    ProductCache::deleteByUniqidOrExpired($cacheKey);

                    $objCache          = ProductCache::createForUniqid($cacheKey);
                    $objCache->expires = $this->getProductCacheExpiration();
                    $objCache->setProductIds($arrIds);
                    $objCache->save();

                    Database::getInstance()->unlockTables();
                }
            } else {
                $arrProducts = $this->findProducts();
            }

            if (!empty($arrProducts)) {
                $arrProducts = $this->generatePagination($arrProducts);
            }
        }

        // No products found
        if (!\is_array($arrProducts) || empty($arrProducts)) {
            $this->compileEmptyMessage();

            return;
        }

        $arrBuffer         = array();
        $arrDefaultOptions = $this->getDefaultProductOptions();

        // Prepare optimized product categories
        $preloadData = $this->batchPreloadProducts();

        /** @var \Isotope\Model\Product\Standard $objProduct */
        foreach ($arrProducts as $objProduct) {
            if ($objProduct instanceof Product\Standard) {
                if (isset($preloadData['categories'][$objProduct->id])) {
                    $objProduct->setCategories($preloadData['categories'][$objProduct->id], true);
                }
                if (!$objProduct->hasAdvancedPrices()) {
                    if ($objProduct->hasVariantPrices() && !$objProduct->isVariant()) {
                        $ids = $objProduct->getVariantIds();
                    } else {
                        $ids = [$objProduct->hasVariantPrices() ? $objProduct->getId() : $objProduct->getProductId()];
                    }

                    $prices = array_intersect_key($preloadData['prices'], array_flip($ids));

                    if (!empty($prices)) {
                        $objProduct->setPrice(new ProductPriceCollection($prices, ProductPrice::getTable()));
                    }
                }
            }

            $arrConfig = $this->getProductConfig($objProduct);

            if (Environment::get('isAjaxRequest')
                && Input::post('AJAX_MODULE') == $this->id
                && Input::post('AJAX_PRODUCT') == $objProduct->getProductId()
                && !$this->iso_disable_options
            ) {
                $content = $objProduct->generate($arrConfig);
                $content = Controller::replaceInsertTags($content, false);

                throw new ResponseException(new Response($content));
            }

            $objProduct->mergeRow($arrDefaultOptions);

            // Must be done after setting options to generate the variant config into the URL
            if ($this->iso_jump_first && Input::getAutoItem('product', false, true) == '') {
                throw new RedirectResponseException($objProduct->generateUrl($arrConfig['jumpTo'], true));
            }

            $arrBuffer[] = array(
                'cssID'     => $objProduct->getCssId(),
                'class'     => $objProduct->getCssClass(),
                'html'      => $objProduct->generate($arrConfig),
                'product'   => $objProduct,
            );
        }

        // HOOK: to add any product field or attribute to mod_iso_productlist template
        if (isset($GLOBALS['ISO_HOOKS']['generateProductList'])
            && \is_array($GLOBALS['ISO_HOOKS']['generateProductList'])
        ) {
            foreach ($GLOBALS['ISO_HOOKS']['generateProductList'] as $callback) {
                $arrBuffer = System::importStatic($callback[0])->{$callback[1]}($arrBuffer, $arrProducts, $this->Template, $this);
            }
        }

        RowClass::withKey('class')
            ->addCount('product_')
            ->addEvenOdd('product_')
            ->addFirstLast('product_')
            ->addGridRows($this->iso_cols)
            ->addGridCols($this->iso_cols)
            ->applyTo($arrBuffer)
        ;

        $this->Template->products = $arrBuffer;
    }
    
    
    
    
    
    
    private function batchPreloadProducts()
    {
        $query = "SELECT c.pid, GROUP_CONCAT(c.page_id) AS page_ids FROM tl_iso_product_category c JOIN tl_page p ON c.page_id=p.id WHERE p.type!='error_403' AND p.type!='error_404'";

        if (!BE_USER_LOGGED_IN) {
            $time = Date::floorToMinute();
            $query .= " AND p.published='1' AND (p.start='' OR p.start<'$time') AND (p.stop='' OR p.stop>'" . ($time + 60) . "')";
        }

        $query .= " GROUP BY c.pid";

        $data = ['categories' => [], 'prices' => []];
        $result = Database::getInstance()->execute($query);

        while ($row = $result->fetchAssoc()) {
            $data['categories'][$row['pid']] = explode(',', $row['page_ids']);
        }

        $t = ProductPrice::getTable();
        $arrOptions = [
            'column' => [
                "$t.config_id=0",
                "$t.member_group=0",
                "$t.start=''",
                "$t.stop=''",
            ],
        ];

        /** @var ProductPriceCollection $prices */
        $prices = ProductPrice::findAll($arrOptions);

        if (null !== $prices) {
            foreach ($prices as $price) {
                if (!isset($data['prices'][$price->pid])) {
                    $data['prices'][$price->pid] = $price;
                }
            }
        }

        return $data;
    }
    
    
    
    
    
    
    
    
    
     // Custom function that returns an array of products that the user previously ordered
    protected function findOrderedProducts($pid, $arrCacheIds = null)
    {
        
        // Stores our templated products and their IDs to prevent duplicates
        $arrProducts = [];
		$arrIds = [];
		
		$arrProducts = Product::findBy(['tl_iso_product.pid=?'],[$pid]);

        
        // Return our templates items/products
        return $arrProducts;
    }
    
    
    


  
}
