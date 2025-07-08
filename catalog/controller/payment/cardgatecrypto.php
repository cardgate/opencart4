<?php
namespace Opencart\Catalog\Controller\Extension\Cardgate\Payment;
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
 * @package     Payment_CardGate
 * @author      Richard Schoots, <info@cardgate.com>
 * @copyright   Copyright (c) 2022 CardGate (http://www.cardgate.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

include_once 'cardgate.php';

class CardGateCrypto extends CardgateGeneric {

    public function index() {
        return $this->_index('cardgatecrypto');
    }

    public function confirm() {
        $this->_confirm('cardgatecrypto');
    }
}