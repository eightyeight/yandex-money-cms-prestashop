<?php
/**
* Module is prohibited to sales! Violation of this condition leads to the deprivation of the license!
*
* @category  Front Office Features
* @package   Yandex Payment Solution
* @author    Yandex.Money <cms@yamoney.ru>
* @copyright © 2015 NBCO Yandex.Money LLC
* @license   https://money.yandex.ru/doc.xml?id=527052
*/

class YamoduleRedirectkModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_column_left = false;
    public $display_column_right = false;
    public $display_footer = false;
    public $ssl = true;

    public function initContent()
    {
        $cart = $this->context->cart;

        $payments = array();
        $payments['message'] = $this->module->l('The order status is not paid! Go to my account and then reorder');
        if ($cart) {
            $total_to_pay = $cart->getOrderTotal(true);
            $rub_currency_id = Currency::getIdByIsoCode('RUB');
            if ($cart->id_currency != $rub_currency_id) {
                $from_currency = new Currency($cart->id_curre1ncy);
                $to_currency = new Currency($rub_currency_id);
                $total_to_pay = Tools::convertPriceFull($total_to_pay, $from_currency, $to_currency);
            }

            $display = '';
            if (Configuration::get('YA_P2P_ACTIVE')) {
                $vars_p2p = Configuration::getMultiple(array(
                    'YA_P2P_NUMBER',
                    'YA_P2P_ACTIVE',
                ));
                $this->context->smarty->assign(array(
                    'DATA_P2P' => $vars_p2p,
                    'price' => number_format($total_to_pay, 2, '.', ''),
                    'cart' => $this->context->cart
                ));

                $display .= $this->display(__FILE__, 'payment.tpl');
            }

            if (Configuration::get('YA_ORG_ACTIVE')) {
                $vars_org = Configuration::getMultiple(array(
                    'YA_ORG_SHOPID',
                    'YA_ORG_SCID',
                    'YA_ORG_ACTIVE',
                    'YA_ORG_TYPE',
                    'YA_SEND_CHECK'
                ));

                $receipt = array(
                    'customerContact' => $this->context->customer->email,
                    'items' => array(),
                );

                $products = $this->context->cart->getProducts(true);
                $taxValue = $this->module->getTaxesArray(true);
                $carrier = new CarrierCore($this->context->cart->id_carrier, $this->context->language->id);
                $summary = $this->context->cart->getSummaryDetails(null, true);

                $disc = 1.0 - round($summary['total_discounts']/$summary['total_products_wt'], 2);

                foreach ($products as $product) {
                    $id_tax = Product::getIdTaxRulesGroupByIdProduct($product['id_product']);
                    $receipt['items'][] = array(
                        'quantity' => $product['cart_quantity'],
                        'text' => substr($product['name'], 0, 128),
                        'tax' => ($taxValue['YA_NALOG_STAVKA_'.$id_tax] ? $taxValue['YA_NALOG_STAVKA_'.$id_tax] : 1),
                        'price' => array(
                            'amount' => number_format($product['price_wt'] * $disc, 2, '.', ''),
                            'currency' => 'RUB'
                        ),
                    );
                }

                if ($carrier->id && $this->context->cart->getPackageShippingCost()) {
                    $id_tax = $carrier->id_tax_rules_group;
                    $receipt['items'][] = array(
                        'quantity' => 1,
                        'text' => substr($carrier->name, 0, 128),
                        'tax' => ($taxValue['YA_NALOG_STAVKA_'.$id_tax] ? $taxValue['YA_NALOG_STAVKA_'.$id_tax] : 1),
                        'price' => array(
                            'amount' => number_format($this->context->cart->getPackageShippingCost(), 2, '.', ''),
                            'currency' => 'RUB'
                        ),
                    );
                }

                $this->context->smarty->assign(array(
                    'DATA_ORG' => $vars_org,
                    'id_cart' => $cart->id,
                    'receipt' => $receipt,
                    'customer' => new Customer($cart->id_customer),
                    'address' => new Address($this->context->cart->id_address_delivery),
                    'total_to_pay' => number_format($total_to_pay, 2, '.', ''),
                    'this_path_ssl' => Tools::getShopDomainSsl(true, true)
                        .__PS_BASE_URI__.'modules/'.$this->module->name.'/',
                    'shop_name' => Configuration::get('PS_SHOP_NAME')
                ));

                $payments = Configuration::getMultiple(array(
                    'YA_ORG_PAYMENT_YANDEX',
                    'YA_ORG_PAYMENT_CARD',
                    'YA_ORG_PAYMENT_MOBILE',
                    'YA_ORG_PAYMENT_WEBMONEY',
                    'YA_ORG_PAYMENT_TERMINAL',
                    'YA_ORG_PAYMENT_SBER',
                    'YA_ORG_PAYMENT_PB',
                    'YA_ORG_PAYMENT_MA',
                    'YA_ORG_PAYMENT_ALFA'
                ));

                if (Configuration::get('YA_ORG_INSIDE')) {
                    $payments['pt'] = Tools::getValue('type');
                } else {
                    $payments['pt'] = '';
                }
            }
        }

        $this->context->smarty->assign($payments);

        return $this->setTemplate('redirectk.tpl');
    }
}
