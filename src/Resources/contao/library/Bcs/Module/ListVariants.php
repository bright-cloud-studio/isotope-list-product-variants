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
    protected $strTemplate = 'mod_iso_list_ordered_products';
    protected $strFormId = 'iso_mod_product_group_list';
    
    public function generate()
    {
		//if($this->enableBatchAdd)
			//$this->strTemplate = 'mod_iso_list_ordered_products_batch';

        return parent::generate();
    }
    
    protected function compile()
    {
        // return message if no filter is set
        if ($this->iso_emptyFilter && !Input::get('isorc') && !Input::get('keywords')) {
            $this->Template->message  = Controller::replaceInsertTags($this->iso_noFilter);
            $this->Template->type     = 'noFilter';
            $this->Template->products = array();

            return;
        }

        global $objPage;
        $cacheKey      = $this->getCacheKey();
        $arrProducts   = null;
        $arrCacheIds   = null;

        // Try to load the products from cache
        if ($this->blnCacheProducts && ($objCache = ProductCache::findByUniqid($cacheKey)) !== null) {
            $arrCacheIds = $objCache->getProductIds();

            // Use the cache if keywords match. Otherwise we will use the product IDs as a "limit" for findProducts()
            if ($objCache->keywords == Input::get('keywords')) {
                $arrCacheIds = $this->generatePagination($arrCacheIds);

                $objProducts = Product::findAvailableByIds($arrCacheIds, array(
                    'order' => Database::getInstance()->findInSet(Product::getTable().'.id', $arrCacheIds)
                ));

                $arrProducts = (null === $objProducts) ? array() : $objProducts->getModels();

                // Cache is wrong, drop everything and run findProducts()
                if (\count($arrProducts) != \count($arrCacheIds)) {
                    $arrCacheIds = null;
                    $arrProducts = null;
                }
            }
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


  
}
