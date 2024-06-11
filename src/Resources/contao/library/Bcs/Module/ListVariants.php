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
        return parent::generate();
    }
    
}
