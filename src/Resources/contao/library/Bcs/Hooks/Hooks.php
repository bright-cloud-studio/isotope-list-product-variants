<?php

namespace Bcs\Hooks;

use Contao\Database;

use Isotope\Interfaces\IsotopeProduct;
use Isotope\Isotope;
use Isotope\Model\Attribute;
use Isotope\Model\AttributeOption;

use Isotope\Model\Product;

class Hooks
{
    protected static $arrUserOptions = array();

    public function onProcessForm($submittedData, $formData, $files, $labels, &$form)
    {

        echo "<pre>";
        print_r($form);
        echp "</pre>";
        die();
        
        if($formData['formID'] == 'bulk_order_csv') {

            echo "SPECIAL FORM FOUND!";
            die();
        }

    }
    
}
