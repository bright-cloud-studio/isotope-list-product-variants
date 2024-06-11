<?php

// Frontend Modules
$GLOBALS['FE_MOD']['bcs']['list_prod_var'] 	= 'Bcs\Module\ListProductVariants';

/* Hooks */
$GLOBALS['TL_HOOKS']['processFormData'][]      = array('Bcs\Hooks\Hooks', 'onProcessForm');
