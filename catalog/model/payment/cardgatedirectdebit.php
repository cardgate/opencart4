<?php
namespace Opencart\Catalog\Model\Extension\Cardgate\Payment;
/**
 * Opencart CardGate payment extension
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
 * @copyright   Copyright (c) 2022 CardGate (http://www.cardgate.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CardGateDirectdebit extends \Opencart\System\Engine\Model {

    public function getMethods( $address ) {
        $currency       = $this->session->data['currency'];
        $bCurrencyCheck = $currency == 'EUR';
        if (!$bCurrencyCheck) return [];

        $this->load->language( 'extension/cardgate/payment/cardgatedirectdebit' );

        if ($this->cart->hasSubscription()) {
            $status = false;
        } elseif (!$this->config->get('payment_cardgatedirectdebit_geo_zone_id')) {
            $status = true;
        } else {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_cardgatedirectdebit_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");
            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }


        if ($this->config->get('payment_cardgatedirectdebit_custom_payment_method_text')){
            $title = trim($this->config->get('payment_cardgatedirectdebit_custom_payment_method_text'));
        } else {
            $title = trim($this->language->get( 'text_title' ));
        }

        $method_data = [];
        if ( $status ) {
            $option_data['cardgatedirectdebit'] = [
                'code' => 'cardgatedirectdebit.cardgatedirectdebit',
                'name' => $this->language->get('heading_title')
            ];

            $method_data = [
                'code'       => 'cardgatedirectdebit',
                'name'       => $title,
                'option'     => $option_data,
                'sort_order' => $this->config->get('payment_cardgatedirectdebit_sort_order')
            ];
        }

        return $method_data;
    }
}
