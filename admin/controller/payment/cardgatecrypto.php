<?php
namespace Opencart\Admin\Controller\Extension\Cardgate\Payment;

include_once 'cardgate.php';

class Cardgatecrypto extends CardgateGeneric {

    public function index() {
        $this->_index('cardgatecrypto');
    }

    public function save() {
        return $this->_save('cardgatecrypto');
    }
}
?>