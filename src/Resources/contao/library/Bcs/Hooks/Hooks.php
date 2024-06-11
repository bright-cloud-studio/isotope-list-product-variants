<?php

namespace Bcs\Hooks;

use Contao\System;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Message;
use Isotope\Model\Config;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\ProductCollection\Order;


class Hooks extends System {
{
    protected static $arrUserOptions = array();

    /* HOOK - Triggered when trying to add a product to the cart on a Product Reader page */
    public function checkCollectionQuantity( Product $objProduct, $intQuantity, IsotopeProductCollection $objCollection ) {

        echo "HOOKY";
        die();
    }
    
}
