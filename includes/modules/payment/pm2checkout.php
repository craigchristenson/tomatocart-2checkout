<?php
/*
  $Id: pm2checkout.php $
  Author: Craig Christenson
  TomatoCart Open Source Shopping Cart Solutions
  http://www.tomatocart.com

  Copyright (c) 2009 Wuxi Elootec Technology Co., Ltd;  Copyright (c) 2006 osCommerce

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License v2 (1991)
  as published by the Free Software Foundation.
*/

  class osC_Payment_pm2checkout extends osC_Payment {
    var $_title,
        $_code = 'pm2checkout',
        $_status = false,
        $_sort_order,
        $_order_id;

    function osC_Payment_pm2checkout() {
      global $osC_Database, $osC_Language, $osC_ShoppingCart;

      $this->_title = $osC_Language->get('payment_pm2checkout_title');
      $this->_method_title = $osC_Language->get('payment_pm2checkout_method_title');
      $this->_status = (MODULE_PAYMENT_PM2CHECKOUT_STATUS == '1') ? true : false;
      $this->_sort_order = MODULE_PAYMENT_PM2CHECKOUT_SORT_ORDER;

      if (MODULE_PAYMENT_PM2CHECKOUT_DIRECT == 1) {
        $this->form_action_url = 'https://www.2checkout.com/checkout/purchase" id="tco_form" target="tco_lightbox_iframe" onclick="showTcoIframe()';
      } else {
        $this->form_action_url = 'https://www.2checkout.com/checkout/purchase';
      }

      if ($this->_status === true) {
        if ((int)MODULE_PAYMENT_PM2CHECKOUT_ORDER_STATUS_ID > 0) {
          $this->order_status = MODULE_PAYMENT_PM2CHECKOUT_ORDER_STATUS_ID;
        }

        if ((int)MODULE_PAYMENT_PM2CHECKOUT_ZONE > 0) {
          $check_flag = false;

          $Qcheck = $osC_Database->query('select zone_id from :table_zones_to_geo_zones where geo_zone_id = :geo_zone_id and zone_country_id = :zone_country_id order by zone_id');
          $Qcheck->bindTable(':table_zones_to_geo_zones', TABLE_ZONES_TO_GEO_ZONES);
          $Qcheck->bindInt(':geo_zone_id', MODULE_PAYMENT_PM2CHECKOUT_ZONE);
          $Qcheck->bindInt(':zone_country_id', $osC_ShoppingCart->getBillingAddress('country_id'));
          $Qcheck->execute();

          while ($Qcheck->next()) {
            if ($Qcheck->valueInt('zone_id') < 1) {
              $check_flag = true;
              break;
            } elseif ($Qcheck->valueInt('zone_id') == $osC_ShoppingCart->getBillingAddress('zone_id')) {
              $check_flag = true;
              break;
            }
          }

          if ($check_flag === false) {
            $this->_status = false;
          }
        }
      }
    }

    function selection() {

      return array('id' => $this->_code,
                   'module' => $this->_method_title);
    }

    function confirmation() {
      $this->_order_id = osC_Order::insert();
    }

    function process_button() {
      global $osC_Customer, $osC_Currencies, $osC_ShoppingCart;

        $currency = $osC_Currencies->getCode();

      switch ($osC_ShoppingCart->getBillingAddress('country_iso_code_3')) {
        case 'USA':
        case 'CAN':
          $state_code = $osC_ShoppingCart->getBillingAddress('state');
          break;

        default:
          $state_code = 'XX';
          break;
      }

      if(MODULE_PAYMENT_PM2CHECKOUT_DEMO_MODE == '1')
          $demo = 'Y';
      else
          $demo = 'N';

      $process_button_string = osc_draw_hidden_field('sid', MODULE_PAYMENT_PM2CHECKOUT_SELLER_ID) .
                               osc_draw_hidden_field('total', $osC_Currencies->formatRaw($osC_ShoppingCart->getTotal(), $currency)) .
                               osc_draw_hidden_field('x_receipt_link_url', osc_href_link(FILENAME_CHECKOUT, 'process', 'SSL', null, null, true)) .
                               osc_draw_hidden_field('first_name', $osC_ShoppingCart->getBillingAddress('firstname')) .
                               osc_draw_hidden_field('last_name', $osC_ShoppingCart->getBillingAddress('lastname')) .
                               osc_draw_hidden_field('street_address', $osC_ShoppingCart->getBillingAddress('street_address')) .
                               osc_draw_hidden_field('city', $osC_ShoppingCart->getBillingAddress('city')) .
                               osc_draw_hidden_field('state', $state_code) .
                               osc_draw_hidden_field('zip', $osC_ShoppingCart->getBillingAddress('postcode')) .
                               osc_draw_hidden_field('country', $osC_ShoppingCart->getBillingAddress('country_iso_code_3')) .
                               osc_draw_hidden_field('phone', $osC_ShoppingCart->getBillingAddress('telephone_number')) .
                               osc_draw_hidden_field('email', $osC_Customer->getEmailAddress()) .
                               osc_draw_hidden_field('ship_name', $osC_ShoppingCart->getShippingAddress('firstname') . ' ' . $osC_ShoppingCart->getShippingAddress('lastname')) .
                               osc_draw_hidden_field('ship_street_address', $osC_ShoppingCart->getShippingAddress('street_address')) .
                               osc_draw_hidden_field('ship_city', $osC_ShoppingCart->getShippingAddress('city')) .
                               osc_draw_hidden_field('ship_state', $osC_ShoppingCart->getShippingAddress('state')) .
                               osc_draw_hidden_field('ship_zip', $osC_ShoppingCart->getShippingAddress('postcode')) .
                               osc_draw_hidden_field('ship_country', $osC_ShoppingCart->getShippingAddress('country_iso_code_3')) .
                               osc_draw_hidden_field('id_type', '1') .
                               osc_draw_hidden_field('demo', $demo) .
                               osc_draw_hidden_field('customer_id', $osC_Customer->getID()) .
                               osc_draw_hidden_field('cart_order_id', $this->_order_id) . 
                               osc_draw_hidden_field('purchase_step', 'payment-method');
                               if (MODULE_PAYMENT_PM2CHECKOUT_DIRECT == 1) {
                                 $process_button_string.= osc_draw_hidden_field('tco_use_inline', '1');
                               }


      $products = array();
      $products = $osC_ShoppingCart->getProducts();
      $i = 1;
      foreach($products as $product) {
      $process_button_string .= osc_draw_hidden_field('c_prod_' . $i, $product['id'] . ',' . $product['quantity']) .
                                   osc_draw_hidden_field('c_name_' . $i, $product['name']) .
                                   osc_draw_hidden_field('c_description_' . $i, $product['name']) .
                                   osc_draw_hidden_field('c_price_' . $i, $product['final_price']);
          $i++;
       }



      return $process_button_string;
    }

    function process() {
      global $osC_Database, $osC_Currencies, $osC_ShoppingCart, $messageStack, $osC_Language;
      
      if (MODULE_PAYMENT_PM2CHECKOUT_DEMO_MODE == 1) {
        $order_number = 1;
      } else {
        $order_number = $_REQUEST['order_number'];
      }

      $check_hash = strtoupper(md5(MODULE_PAYMENT_PM2CHECKOUT_SECRET_WORD . MODULE_PAYMENT_PM2CHECKOUT_SELLER_ID . $order_number . $osC_Currencies->formatRaw($osC_ShoppingCart->getTotal())));

      if ($check_hash == $_REQUEST['key']) {
        if (isset($_REQUEST['cart_order_id']) && is_numeric($_REQUEST['cart_order_id']) && ($_REQUEST['cart_order_id'] > 0)) {
          $Qcheck = $osC_Database->query('select orders_status, currency, currency_value from :table_orders where orders_id = :orders_id and customers_id = :customers_id');
          $Qcheck->bindTable(':table_orders', TABLE_ORDERS);
          $Qcheck->bindInt(':orders_id', $_REQUEST['cart_order_id']);
          $Qcheck->bindInt(':customers_id', $_REQUEST['customer_id']);
          $Qcheck->execute();

          if ($Qcheck->numberOfRows() > 0) {
            $Qtotal = $osC_Database->query('select value from :table_orders_total where orders_id = :orders_id and class = "total" limit 1');
            $Qtotal->bindTable(':table_orders_total', TABLE_ORDERS_TOTAL);
            $Qtotal->bindInt(':orders_id', $_REQUEST['cart_order_id']);
            $Qtotal->execute();

            $comments = '2Checkout Order Successful [' . $_REQUEST['order_number'] . '; ' . $osC_Currencies->format($_REQUEST['total']) . ')]';

            osC_Order::process($_REQUEST['cart_order_id'], $this->order_status, $comments);
          }
        }
      } else {
        $comments =  "MD5 HASH MISMATCH, PLEASE CONTACT THE SELLER";
        
        $messageStack->add_session('checkout', $comments);
        
        osC_Order::insertOrderStatusHistory($_REQUEST['cart_order_id'], $this->order_status, $comments);
        
        osc_redirect(osc_href_link(FILENAME_CHECKOUT, 'checkout&view=paymentInformationForm', 'SSL'));
      }
    }
  }
?>
