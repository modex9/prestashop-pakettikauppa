<?php
/**
 * 2017-2018 Pakettikauppa
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Pakettikauppa <asiakaspalvelu@pakettikauppa.fi>
 * @copyright 2017- Pakettikauppa Oy
 * @license   https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 */

require_once(dirname(__FILE__) . '../../../config/config.inc.php');
require_once(dirname(__FILE__) . '../../../init.php');

include_once(dirname(__FILE__) . '/init.php');
$class_pakettikauppa = new PS_Pakettikauppa();

ob_clean();

switch (Tools::getValue('action')) {
    case 'FetchData' :
        $selected_sql = "SELECT wc.`id_carrier`,c.name FROM `" . _DB_PREFIX_ . "warehouse_carrier` wc inner join " . _DB_PREFIX_ . "carrier c on wc.`id_carrier`=c.`id_reference` WHERE wc.`id_warehouse`='" . Tools::getValue('id_warehouse') . "' AND c.`external_module_name`='pakettikauppa' AND c.`deleted`=0";
        $selected_carriers = DB::getInstance()->ExecuteS($selected_sql);
        $carriers = DB::getInstance()->ExecuteS("SELECT `id_reference`,`name` FROM `" . _DB_PREFIX_ . "carrier` WHERE is_module=1 and `external_module_name`='pakettikauppa' and deleted=0 and `id_reference` not in (" . str_replace(',c.name', '', $selected_sql) . ")");

        echo json_encode(array('selected_carriers' => $selected_carriers, 'carriers' => $carriers));

        break;

    case 'searchPickUpPoints':
        $client = $class_pakettikauppa->api->load_client();
        $cart = Context::getContext()->cart;

        $selected_method = $class_pakettikauppa->sql->get_single_row(array(
          'table' => 'main',
          'get_values' => array(
            'code' => 'method_code',
          ),
          'where' => array(
            'id_cart' => $cart->id,
          ),
        ));

        $current_values = $class_pakettikauppa->sql->get_single_row(array(
            'table' => 'main',
            'get_values' => array(
                'point' => 'pickup_point_id',
                'method' => 'method_code',
            ),
            'where' => array(
                'id_cart' => $cart->id,
            ),
        ));

        $address = new Address($cart->id_address_delivery);
        $country_iso = Country::getIsoById($address->id_country);

        $pickups_number = Configuration::get('PAKETTIKAUPPA_MAX_PICKUPS');
        if (empty($pickups_number)) $pickups_number = 5;
        $result = $client->searchPickupPoints(Tools::getValue('postcode'), null, $country_iso, $selected_method['code'], $pickups_number);

        echo json_encode(array(
            'pickup_points' => $result,
            'selected' => $current_values['point'],
        ));
        break;

    case 'selectPickUpPoints':
        $check_method = $class_pakettikauppa->sql->get_single_row(array(
            'table' => 'methods',
            'where' => array(
                'method_code' => Tools::getValue('method_code'),
            ),
        ));
        $selected_pickup_point = (Tools::getValue('id_pickup') !== "") ? Tools::getValue('id_pickup') : 0;
        if (!empty($check_method)) {
            $result = $class_pakettikauppa->sql->insert_row(array(
                'table' => 'main',
                'values' => array(
                    'id_cart' => Tools::getValue('id_cart'),
                    'pickup_point_id' => $selected_pickup_point,
                    'id_carrier' => rtrim(Tools::getValue('id_carrier')),
                    'method_code' => Tools::getValue('method_code'),
                ),
                'on_duplicate' => array(
                    'pickup_point_id' => $selected_pickup_point,
                    'id_carrier' => preg_replace('/[^0-9]/', '', Tools::getValue('id_carrier')),
                    'method_code' => Tools::getValue('method_code'),
                ),
            )); 

            if ($result) {
                echo true;
            }
        }
        break;
    case 'saveAddtionalService':
        if (empty(Tools::getValue('id_cart'))) {
            die('Cart ID not received');
        }

        $selected_services = Tools::getValue('selected_services');

        $result = $class_pakettikauppa->sql->update_row(array(
            'table' => 'main',
            'update' => array(
                'additional_services' => (!empty($selected_services)) ? serialize($selected_services) : '',
            ),
            'where' => array(
                'id_cart' => Tools::getValue('id_cart'),
            ),
        ));
        if ($result) {
            echo true;
        }
        break;
    default:
        echo 'Action not exists';
}
