<?php

/**
 * @category    Nooe
 * @package     Nooe_Connector
 * @author      NOOE Team <dev@nooestores.com>
 * @copyright   Copyright(c) 2022 NOOE (https://www.nooestores.com)
 * @license     https://opensource.org/licenses/gpl-3.0.html GNU General Public License (GPL 3.0)
 */

namespace Nooe\Connector\Plugin;

class ApplyShipping
{
	/**
	 * Disables "nooeshipping" shipping method on the frontend side.
	 *
	 * @param \Magento\Shipping\Model\Shipping $subject
	 * @param \Closure $proceed
	 * @param string $carrierCode
	 * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
	 * @return mixed
	 */
	public function aroundCollectCarrierRates(
		\Magento\Shipping\Model\Shipping $subject,
		\Closure $proceed,
		$carrierCode,
		$request
	) {
		if ($carrierCode == 'nooe_shipping') {
			return false;
		}

		return $proceed($carrierCode, $request);
	}
}