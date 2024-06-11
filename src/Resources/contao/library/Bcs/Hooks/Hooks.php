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


class Hooks extends System
{
    protected static $arrUserOptions = array();

    /* HOOK - Triggered when trying to add a product to the cart on a Product Reader page */
    public function checkCollectionQuantity( Product $objProduct, $intQuantity, IsotopeProductCollection $objCollection ) {
        
        /*
        echo "<pre>";
        print_r($objProduct);
        echo "</pre><br><hr><br>";
        
        echo "<pre>";
        print_r($intQuantity);
        echo "</pre><br><hr><br>";
        
        echo "<pre>";
        print_r($objCollection);
        echo "</pre><br><hr><br>";
        */
        
    }







    public function onProcessForm($submittedData, $formData, $files, $labels, $form)
    {
        echo "BLAM";
        die();

        if($formData['formID'] == 'directory_submission') {
        }
    }






    
    
}
