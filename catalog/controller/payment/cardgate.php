<?php
    namespace Opencart\Catalog\Controller\Extension\Cardgate\Payment;

    /**
     * Opencart CardGatePlus payment extension
     *
     * NOTICE OF LICENSE
     *
     * This source file is subject to the Open Software License (OSL 3.0)
     * that is bundled with this package in the file LICENSE.txt.
     * It is also available through the world-wide-web at this URL:
     * http://opensource.org/licenses/osl-3.0.php
     *
     * @category    Payment
     * @package     Payment_CardGatePlus
     * @author      Richard Schoots, <info@cardgate.com>
     * @copyright   Copyright (c) 2016 CardGatePlus   (http://www.cardgate.com)
     * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
     */
    class CardgateGeneric extends \Opencart\System\Engine\Controller {
        // Also adjust the version in Opencart\Admin\Controller\Extension\Cardgate\Payment\CardgateGeneric
        protected $version = '4.0.9';

        /**
         * Index action
         */
        public function _index($payment) {
            $this->load->language ( 'extension/cardgate/payment/' . $payment );

            $data ['button_confirm'] = $this->language->get ( 'button_confirm' );
            $data ['redirect_message'] = $this->language->get ( 'text_redirect_message' );
            $data ['text_select_payment_method'] = $this->language->get ( 'text_select_payment_method' );
            $data['language'] = $this->config->get('config_language');

            return $this->load->view ( 'extension/cardgate/payment/' . $payment, $data );
        }

        /**
         * Check and register the Order and set to intialized mode
         */
        public function _confirm($payment) {
            $this->load->language('extension/cardgate/payment/'.$payment);

            $this->load->model( 'checkout/order' );
            $this->load->model( 'account/address' );

            $order_info = $this->model_checkout_order->getOrder( $this->session->data ['order_id'] );
            $json = [];
            if (!$this->hasBillingAddress($order_info)){
                $json['error'] = 'CardGate error: Billing address needs to be set in the checkout';
                $this->response->setOutput ( json_encode ( $json ) );
            }

            if (!$json) {
                try {
                    include 'cardgate-clientlib-php/init.php';
                    $amount   = ( int ) round( $this->currency->format( $order_info ['total'], $order_info ['currency_code'], false, false ) * 100, 0 );
                    $currency = strtoupper( $order_info ['currency_code'] );
                    $option   = substr( $payment, 8 );
                    $option = $option== 'przelewy'? 'przelewy24': $option;

                    $oCardGate = new \cardgate\api\Client ( ( int ) $this->config->get( 'payment_cardgate_merchant_id' ), $this->config->get( 'payment_cardgate_api_key' ), ( $this->config->get( 'payment_cardgate_test_mode' ) == 'test' ? true : false ) );
                    $oCardGate->setIp( $_SERVER ['REMOTE_ADDR'] );
                    $oCardGate->setLanguage( $this->language->get( 'code' ) );
                    $oCardGate->version()->setPlatformName( 'Opencart' );
                    $oCardGate->version()->setPlatformVersion( VERSION );
                    $oCardGate->version()->setPluginName( 'Opencart_CardGate' );
                    $oCardGate->version()->setPluginVersion( $this->version );

                    $iSiteId = ( int ) $this->config->get( 'payment_cardgate_site_id' );

                    $oTransaction = $oCardGate->transactions()->create( $iSiteId, $amount, $currency );

                    // Configure payment option.
                    $oTransaction->setPaymentMethod( $oCardGate->methods()->get( $option ) );

                    // Configure customer.
                    $oConsumer = $oTransaction->getConsumer();
                    $oConsumer->setEmail( $order_info ['email'] );
                    isset( $order_info['telephone'] ) ?? $oConsumer->setPhone( $order_info['telephone'] );
                    $address = $this->session->data['payment_address'];
                    $oConsumer->address()->setFirstName( $address ['firstname'] );
                    $oConsumer->address()->setLastName( $address ['lastname'] );
                    $oConsumer->address()->setAddress( $address['address_1'] );
                    $oConsumer->address()->setZipCode( $address['postcode'] );
                    $oConsumer->address()->setCity( $address ['city'] );
                    $oConsumer->address()->setCountry( $address ['iso_code_2'] );

                    if ( $this->cart->hasShipping() ) {
                        $address = $this->session->data['shipping_address'];
                        $oConsumer->shippingAddress()->setFirstName( $address ['firstname'] );
                        $oConsumer->shippingAddress()->setLastName( $address ['lastname'] );
                        $oConsumer->shippingAddress()->setAddress( $address ['address_1'] );
                        $oConsumer->shippingAddress()->setZipCode( $address ['postcode'] );
                        $oConsumer->shippingAddress()->setCity( $address ['city'] );
                        $oConsumer->shippingAddress()->setCountry( $address ['iso_code_2'] );
                    }

                    $products        = $this->cart->getProducts();
                    $cart_item_total = 0;
                    $vat_total       = 0;
                    $shipping_tax    = 0;

                    $oCart = $oTransaction->getCart();

                    foreach ( $products as $product ) {
                        $price        = $this->convertAmount( $this->tax->calculate( $product ['price'], $product ['tax_class_id'], false ), $order_info['currency_code'] );
                        $price_wt     = $this->convertAmount( $this->tax->calculate( $product ['price'], $product ['tax_class_id'], true ), $order_info ['currency_code'] );
                        $vat          = $this->tax->getTax( $price, $product ['tax_class_id'] );
                        $vat_perc     = round( $vat / $price, 2 );
                        $vat_per_item = round( $price_wt - $price, 0 );
                        $oItem        = $oCart->addItem( \cardgate\api\Item::TYPE_PRODUCT, $product ['model'], $product ['name'], $product ['quantity'], $price_wt );
                        $oItem->setVat( $vat_perc );
                        $oItem->setVatAmount( $vat_per_item );
                        $oItem->setVatIncluded( 1 );
                        $vat_total       += round( $vat_per_item * $product ['quantity'], 0 );
                        $cart_item_total += round( $price * $product ['quantity'], 0 );
                    }

                    if ( $this->cart->hasShipping() && ! empty ( $this->session->data ['shipping_method'] ) ) {

                        $shipping_data = $this->session->data['shipping_method'];

                        if ( ! empty( $shipping_data['cost'] && $shipping_data['cost'] > 0 ) ) {
                            $price        = $this->convertAmount( $this->tax->calculate( $shipping_data ['cost'], $shipping_data ['tax_class_id'], false ), $order_info ['currency_code'] );
                            $price_wt     = $this->convertAmount( $this->tax->calculate( $shipping_data ['cost'], $shipping_data ['tax_class_id'], true ), $order_info ['currency_code'] );
                            $vat          = $this->tax->getTax( $price, $shipping_data ['tax_class_id'] );
                            $vat_perc     = round( $vat / $price, 2 );
                            $vat_per_item = round( $price_wt - $price, 0 );
                            $shipping_tax = $vat_per_item;
                            $oItem        = $oCart->addItem( \cardgate\api\Item::TYPE_SHIPPING, $shipping_data ['code'], $shipping_data ['name'], 1, $price_wt );
                            $oItem->setVat( $vat_perc );
                            $oItem->setVatAmount( $vat_per_item );
                            $oItem->setVatIncluded( 1 );
                            $vat_total       += $vat_per_item;
                            $cart_item_total += round( $price * 1, 0 );
                        }
                    }

                    if ( isset ( $this->session->data ['voucher'] ) && $this->session->data ['voucher'] > 0 ) {
                        $code          = $this->session->data ['voucher'];
                        $voucher_query = $this->db->query( "SELECT `voucher_id`, `amount` FROM `" . DB_PREFIX . "voucher` WHERE `code` = '" . $code . "'" );
                        $voucher       = $voucher_query->row;
                        $sku           = 'voucher_id_' . $voucher ['voucher_id'];
                        $price         = round( ( int ) - 1 * $voucher ['amount'] * 100, 0 );
                        $oItem         = $oCart->addItem( \cardgate\api\Item::TYPE_DISCOUNT, $sku, 'gift_certificate', 1, $price );
                        $oItem->setVat( 0 );
                        $oItem->setVatIncluded( 0 );
                        $cart_item_total += $price;
                    }

                    if ( isset ( $this->session->data ['coupon'] ) && $this->session->data ['coupon'] > 0 ) {
                        $order_id     = ( int ) $this->session->data ['order_id'];
                        $code         = $this->session->data ['coupon'];
                        $coupon_query = $this->db->query( "SELECT `code`, `value`, `title` FROM `" . DB_PREFIX . "order_total` WHERE `code` = 'coupon' AND `order_id`=" . $order_id );
                        $coupon       = $coupon_query->row;
                        $price        = round( $coupon ['value'] * 100, 0 );
                        $oItem        = $oCart->addItem( \cardgate\api\Item::TYPE_DISCOUNT, $coupon ['code'], $coupon ['title'], 1, $price );
                        $oItem->setVat( 0 );
                        $oItem->setVatIncluded( 0 );
                        $cart_item_total += $price;
                    }

                    $item_difference = $amount - $cart_item_total;

                    $aTaxTotals = $this->cart->getTaxes();
                    $tax_total  = 0;
                    foreach ( $aTaxTotals as $total ) {
                        $tax_total += $total;
                    }

                    $tax_total = $this->convertAmount( $tax_total, $order_info['currency_code'] );
                    $tax_total += $shipping_tax;

                    $tax_difference = $tax_total - $vat_total;

                    if ( $tax_difference != 0 ) {
                        $item  = array();
                        $price = $tax_difference;
                        $oItem = $oCart->addItem( \cardgate\api\Item::TYPE_PAYMENT, 'VAT_correction', 'correction', 1, $tax_difference );
                        $oItem->setVat( 100 );
                        $oItem->setVatAmount( $tax_difference );
                        $oItem->setVatIncluded( 1 );
                    }
                    $item_difference = $amount - $cart_item_total - $vat_total - $tax_difference;

                    if ( $item_difference != 0 ) {
                        $item  = array();
                        $price = $item_difference;
                        $oItem = $oCart->addItem( \cardgate\api\Item::TYPE_PRODUCT, 'pr_correction', 'correction', 1, $item_difference );
                        $oItem->setVat( 0 );
                        $oItem->setVatAmount( 0 );
                        $oItem->setVatIncluded( 1 );
                    }

                    $oTransaction->setCallbackUrl( $this->url->link( 'extension/cardgate/payment/' . $payment . '|callback' ) );
                    $oTransaction->setSuccessUrl( $this->url->link( 'extension/cardgate/payment/' . $payment . '|success' ) );
                    $oTransaction->setFailureUrl( $this->url->link( 'extension/cardgate/payment/' . $payment . '|cancel' ) );
                    $oTransaction->setReference( $order_info ['order_id'] . '|' . $this->request->cookie[ $this->config->get( 'session_name' ) ] );
                    $oTransaction->setDescription( 'Order ' . $order_info ['order_id'] );
                    $oTransaction->register ();

                    $sActionUrl = $oTransaction->getActionUrl();

                    if ( null !== $sActionUrl ) {
                        $json ['status']   = 'success';
                        $json ['redirect'] = trim( $sActionUrl );
                    } else {
                        $json ['status'] = 'failed';
                        $json ['error']  = 'The transaction failed at CardGate.';
                    }
                } catch ( \cardgate\api\Exception $oException_ ) {
                    $json ['status'] = 'failed';
                    $json ['error']  = 'CardGate error: ' . htmlspecialchars( $oException_->getMessage() );
                }
            }
            $this->response->addHeader ( 'Content-Type: application/json' );
            $this->response->setOutput ( json_encode ( $json ) );
        }

        /**
         * @param $order_info
         *
         * @return bool
         */
        public function hasBillingAddress($order_info){
            $result = false;
            if (!empty($order_info['payment_firstname']) &&
                !empty($order_info['payment_lastname']) &&
                !empty($order_info['payment_address_1']) &&
                !empty($order_info['payment_city']) &&
                !empty($order_info['payment_postcode']) &&
                !empty($order_info['payment_country_id'])) {
                $result = true;
            }
            return $result;
        }

        /**
         * After a failed transaction a customer will be send here
         */
        public function cancel() {
            $this->load->model( 'checkout/order' );
            $data = $_REQUEST;
            if ($data['code'] == 309){
                //canceled
                $this->response->redirect ( $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'), true) );
            } else {
                //failed
                $this->response->redirect( $this->url->link( 'checkout/failure', 'language=' . $this->config->get( 'config_language' ), true ) );
            }
        }

        /**
         * After a successful transaction a customer will be send here
         */
        public function success() {
            $this->response->redirect( $this->url->link( 'checkout/success', 'language=' . $this->config->get( 'config_language' ), true ) );
        }

        /**
         * Callback URL called by gateway
         */
        public function callback() {

            $data = $_REQUEST;
            $payment = $data['pt'];
            $dataArray = explode('|',$data['reference']);
            $data['order_id'] = $dataArray[0];
            $data['session_id'] = $dataArray[1];

            $this->load->language('extension/cardgate/payment/'.$payment);
            $json = array ();

            try {
                include 'cardgate-clientlib-php/init.php';
                $sSiteKey = $this->config->get ( 'payment_cardgate_hash_key' );

                $oCardGate = new \cardgate\api\Client ( ( int ) $this->config->get ( 'payment_cardgate_merchant_id' ), $this->config->get ( 'payment_cardgate_api_key' ), ($this->config->get ( 'payment_cardgate_test_mode' ) == 'test' ? TRUE : FALSE) );

                if (FALSE == $oCardGate->transactions ()->verifyCallback ( $data, $sSiteKey )) {
                    $store_name = $this->config->get ( 'config_name' );
                    $mail = new \Opencart\System\Library\Mail($this->config->get('config_mail_engine'));
                    $mail->parameter = $this->config->get('config_mail_parameter');
                    $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
                    $mail->smtp_username = $this->config->get('config_mail_smtp_username');
                    $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
                    $mail->smtp_port = $this->config->get('config_mail_smtp_port');
                    $mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');
                    $mail->setTo ( $this->config->get ( 'config_email' ) );
                    $mail->setFrom ( $this->config->get ( 'config_email' ) );
                    $mail->setSender ( $store_name );
                    $mail->setSubject ( html_entity_decode ( 'Hash check fail ' . $store_name ), ENT_QUOTES, 'UTF-8' );
                    $mail->setText ( html_entity_decode ( 'A payment was not completed because of a hash check fail. Please see the details below.' . print_r ( $data, true ) . 'It could be that the amount or currency does not match for this order.', ENT_QUOTES, 'UTF-8' ) );
                    $mail->send ();
                    die ( 'invalid callback' );
                } else {
                    $this->load->language ( 'extension/cardgate/payment/cardgate'.$payment );
                    $this->load->model ( 'checkout/order' );
                    $order = $this->model_checkout_order->getOrder ( $data ['order_id'] );
                    $complete_status = $this->config->get ( 'payment_cardgate'.$payment.'_order_status_id' );
                    $failed_status = $this->getOrderStatus('Failed');
                    $comment = '';

                    if ($data['code'] == 0){
                        $status = $this->getOrderStatus('Pending');
                    }

                    if ($data ['code'] >= '200' && $data ['code'] < '300') {
                        $status = $complete_status;
                        $comment .= $this->language->get ( 'text_payment_complete' );
                    }

                    if ($data ['code'] >= '300' && $data ['code'] < '400') {
                        if ($data ['code'] == '309') {
                            $status = $order ['order_status_id'];
                        } else {
                            $status = $failed_status;
                            $comment .= $this->language->get ( 'text_payment_failed' );
                        }
                    }

                    if ($data ['code'] >= '700' && $data ['code'] < '800') {
                        $status = $this->getOrderStatus('Pending');
                        $comment .= $this->language->get ( 'text_payment_pending' );
                    }

                    $comment .= '  ' . $this->language->get ( 'text_transaction_nr' );
                    $comment .= ' ' . $data ['transaction'];

                    $this->load->model('checkout/order');
                    $this->load->model('checkout/cart');

                    if ($order ['order_status_id'] != $complete_status) {
                        $this->model_checkout_order->addHistory ( $order ['order_id'], $status, $comment, true );
                        if ($order ['order_status_id'] == $complete_status) {
                            $this->removeCart( $data['session_id'] );
                        }
                        echo $data ['transaction'] . '.' . $data ['code'];
                    } else {
                        echo 'Order already completed.';

                    }
                }
            } catch ( \cardgate\api\Exception $oException_ ) {
                echo htmlspecialchars ( $oException_->getMessage () );
            }
        }

        public function returnJson($message) {
            $json = array ();
            $json ['success'] = false;
            $json ['error'] = $message;
            $this->response->addHeader ( 'Content-Type: application/json' );
            $this->response->setOutput ( json_encode ( $json ) );
        }
        private function convertAmount($amount, $currency_code){
            return round($this->currency->format ( $amount, $currency_code, false, false ) * 100, 0 );
        }

        private function removeCart(string $session_id): void {
            $query = $this->db->query("SELECT `data` FROM `" . DB_PREFIX . "session` WHERE `session_id` = '" . $session_id ."' LIMIT 1");
            if ($query->num_rows) {
                $oData = json_decode($query->row['data']);

                unset($oData->order_id);
                unset($oData->payment_address);
                unset($oData->payment_method);
                unset($oData->payment_methods);
                unset($oData->shipping_address);
                unset($oData->shipping_method);
                unset($oData->shipping_methods);
                unset($oData->comment);
                unset($oData->coupon);
                unset($oData->reward);
                unset($oData->voucher);
                unset($oData->vouchers);

                $data = json_encode($oData);
                $this->db->query("UPDATE `". DB_PREFIX . "session` SET `data` = '".$data."' WHERE `session_id` = '" . $session_id ."'");
                $this->db->query("DELETE FROM `". DB_PREFIX . "cart` WHERE `session_id` = '" . $session_id ."'");
            }
        }
        private function getOrderStatus($status){
            $orderResult = $this->db->query("SELECT `order_status_id` FROM `". DB_PREFIX . "order_status`  WHERE `name` = '" . $status ."' LIMIT 1");
            if ($orderResult->num_rows) {
                return $orderResult->row['order_status_id'];
            } else {
                return 0;
            }
        }
    }
