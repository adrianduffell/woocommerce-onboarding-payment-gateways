<?php
/**
 * @license MIT
 *
 * Modified by woocommerce on 16-October-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace Automattic\WooCommerce\Eway\Vendors\Eway\Rapid\Enum;

/**
 * Defines the possible types of shiping used for a transaction
 */
abstract class ShippingMethod extends AbstractEnum
{
    const UNKNOWN = 'Unknown';
    const LOW_COST = 'LowCost';
    const DESIGNATED_BY_CUSTOMER = 'DesignatedByCustomer';
    const INTERNATIONAL = 'International';
    const MILITARY = 'Military';
    const NEXT_DAY = 'NextDay';
    const STORE_PICKUP = 'StorePickup';
    const TWO_DAY_SERVICE = 'TwoDayService';
    const THREE_DAY_SERVICE = 'ThreeDayService';
    const OTHER = 'Other';
}
