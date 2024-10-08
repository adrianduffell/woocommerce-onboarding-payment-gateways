<?php
/**
 * @license MIT
 *
 * Modified by woocommerce on 16-October-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace Automattic\WooCommerce\Eway\Vendors\Eway\Rapid\Enum;

/**
 * This defines what method will be used by the transaction.
 */
abstract class PaymentMethod extends AbstractEnum
{
    const PROCESS_PAYMENT = 'ProcessPayment';
    const CREATE_TOKEN_CUSTOMER = 'CreateTokenCustomer';
    const UPDATE_TOKEN_CUSTOMER = 'UpdateTokenCustomer';
    const TOKEN_PAYMENT = 'TokenPayment';
    const AUTHORISE = 'Authorise';
}
