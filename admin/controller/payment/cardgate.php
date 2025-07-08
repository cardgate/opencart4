<?php
namespace Opencart\Admin\Controller\Extension\Cardgate\Payment;

class Cardgate extends CardgateGeneric {
    public $error = array();

    public function index() {
        return ($this->_index('cardgate'));
    }

    public function save() {
        return $this->_save('cardgate');
    }
}

class CardgateGeneric extends \Opencart\System\Engine\Controller {
    // Also adjust the version in Opencart\Catalog\Controller\Extension\Cardgate\Payment\CardgateGeneric
    protected $payment;
    protected $version = '4.0.9';

    public function _index($payment): void {
        $this->load->language( 'extension/cardgate/payment/' . $payment );
        $this->document->setTitle( $this->language->get( 'heading_title' ) );
        $this->load->model( 'setting/setting' );
        $this->payment = $payment;

        $tokenParameter = 'user_token=' . $this->session->data['user_token'];

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', $tokenParameter),
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', $tokenParameter . '&type=payment'),
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/cardgate/payment/'.$payment, $tokenParameter),
        );

        $data['action'] = $this->url->link( 'extension/payments/' . $payment, $tokenParameter);
        $data['cancel'] = $this->url->link( 'marketplace/extension', $tokenParameter . '&type=payment');
        $data['save'] = $this->url->link('extension/cardgate/payment/'.$payment.'|save', $tokenParameter);
        $data['back'] = $this->url->link('marketplace/extension', $tokenParameter . '&type=payment');

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data = array_merge($data, $this->getPaymentVariables($payment));

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/cardgate/payment/'.$payment, $data));
    }

    private function getPaymentVariables($payment){
        $data=[];
        if ($payment == 'cardgate'){
            $data[ $this->key( 'test_mode' ) ]                  = $this->config->get( $this->key( 'test_mode' ) );
            $data[ $this->key( 'site_id' ) ]                    = $this->config->get( $this->key( 'site_id' ) );
            $data[ $this->key( 'hash_key' ) ]                   = $this->config->get( $this->key( 'hash_key' ) );
            $data[ $this->key( 'merchant_id' ) ]                = $this->config->get( $this->key( 'merchant_id' ) );
            $data[ $this->key( 'api_key' ) ]                    = $this->config->get( $this->key( 'api_key' ) );
            $data[ $this->key( 'order_description' ) ]          = $this->config->get( $this->key( 'order_description' ) );
            $data[ $this->key( 'plugin_version' ) ]             = $this->version;
        } else {
            $data[ $this->key( 'custom_payment_method_text' ) ] = $this->config->get( $this->key( 'custom_payment_method_text' ) );
            $data[ $this->key( 'total' ) ]                      = $this->config->get( $this->key( 'total' ) );
            $data[ $this->key( 'order_status_id' ) ]            = (empty($this->config->get( $this->key( 'order_status_id' )) ) ? 2 : $this->config->get( $this->key( 'order_status_id' ))) ;
            $data[ $this->key( 'geo_zone_id' ) ]                = $this->config->get( $this->key( 'geo_zone_id' ) );
            $data[ $this->key( 'status' ) ]                     = $this->config->get( $this->key( 'status' ) );
            $data[ $this->key( 'sort_order' ) ]                 = $this->config->get( $this->key( 'sort_order' ) );
        }

        return $data;
    }

    public function _save ($payment): void {
        $this->load->language('extension/cardgate/payment/'.$payment);
        $json = [];

        if ($payment == 'cardgate') {
            $post = $this->request->post;
            if ( $post['payment_cardgate_site_id'] == "" ) {
                 $json['error']['site_id'] = $this->language->get('error_site_id');
            }
            if ( $post['payment_cardgate_hash_key'] == "" ) {
                $json['error']['hash_key'] = $this->language->get( 'error_hash_key' );
            }
            if ( $post['payment_cardgate_merchant_id'] == "" ) {
                $json['error']['merchant_id'] = $this->language->get( 'error_merchant_id' );
            }
            if ( $post['payment_cardgate_api_key'] == "" ) {
                $json['error']['api_key']= $this->language->get( 'error_api_key' );
            }
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_'.$payment, $this->request->post);

            $json['success'] = $this->language->get('text_success');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function key( $variable ) {
        return 'payment_'.$this->payment.'_'.$variable;
    }
}
?>