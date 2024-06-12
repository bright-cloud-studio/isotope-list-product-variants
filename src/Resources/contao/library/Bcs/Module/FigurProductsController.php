<?php

declare(strict_types=1);

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

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\Template;
use Haste\Input\Input;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Model\Product;
use Isotope\Model\Product\AbstractProduct;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;




use Contao\FrontendUser;

class FigurProductsController extends AbstractFrontendModuleController
{
    private IsotopeProduct|null $currentProduct = null;

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null): Response
    {
        $this->currentProduct = Product::findAvailableByIdOrAlias(Input::getAutoItem('product'));

        if (null === $this->currentProduct) {
            return new Response();
        }

        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        $buffer = [];
        $related = Product::findPublishedBy('pid', $this->currentProduct->id);

        foreach ($related as $product) {
            $arrConfig = $this->getProductConfig($product, $model);
            $arrConfig['jumpTo'] = $GLOBALS['objIsotopeListPage'] ?: $GLOBALS['objPage'];

            if (
                Environment::get('isAjaxRequest')
                && Input::post('AJAX_MODULE') === $model->id
                && Input::post('AJAX_PRODUCT') === $product->getProductId()
                && !$model->iso_disable_options
            ) {
                throw new ResponseException(new Response($product->generate($arrConfig)));
            }

            $buffer[] = [
                'cssID' => $product instanceof AbstractProduct ? $product->getCssId() : '',
                'class' => $product instanceof AbstractProduct ? $product->getCssClass() : '',
                'html' => $product->generate($arrConfig),
                'product' => $product,
            ];
        }

        $template->products = $buffer;

        return $template->getResponse();
    }

    protected function getProductConfig(IsotopeProduct $product, ModuleModel $model): array
    {
        $type = $product->getType();

        return [
            'module' => $model,
            'template' => $model->iso_list_layout ?: $type->list_template,
            'gallery' => 0,
            'buttons' => StringUtil::deserialize($model->iso_buttons, true),
            'useQuantity' => $model->iso_use_quantity,
            'disableOptions' => true,
            // 'jumpTo' => $model->findJumpToPage($product),
        ];
    }
}
