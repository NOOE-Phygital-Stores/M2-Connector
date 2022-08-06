<?php

/**
 * @category    Nooe
 * @package     Nooe_M2_Connector
 * @author      Tun2U Team <dev@tun2u.com>
 * @copyright   Copyright(c) 2022 Tun2U (https://www.tun2u.com)
 * @license     https://opensource.org/licenses/gpl-3.0.html  GNU General Public License (GPL 3.0)
 */

namespace Nooe\M2Connector\Helper;


class Sync
{
	/**
	 * @var \Magento\Framework\Module\ResourceInterface
	 */
	protected $moduleResource;

	public function __construct(
		\Magento\Framework\Module\ResourceInterface $moduleResource
	) {
		$this->moduleResource = $moduleResource;
	}


	public function printHeading()
	{
		$version = $this->moduleResource->getDbVersion('Nooe_M2Connector');

		echo "  _   _  ___   ___  _____    ____ ___  _   _ _   _ _____ ____ _____ ___  ____  ";
		echo " | \ | |/ _ \ / _ \| ____|  / ___/ _ \| \ | | \ | | ____/ ___|_   _/ _ \|  _ \ ";
		echo " |  \| | | | | | | |  _|   | |  | | | |  \| |  \| |  _|| |     | || | | | |_) |";
		echo " | |\  | |_| | |_| | |___  | |__| |_| | |\  | |\  | |__| |___  | || |_| |  _ < ";
		echo " |_| \_|\___/ \___/|_____|  \____\___/|_| \_|_| \_|_____\____| |_| \___/|_| \_\\";
		echo "                                                                               v" . $version . "\n";
		echo "\n\n\n";

		echo "Sync\n\n";
	}

	public function show_status($done, $total, $size = 30)
	{
		static $start_time;

		// if we go over our bound, just ignore it
		if ($done > $total) {
			return;
		}

		if (empty($start_time)) {
			$start_time = time();
		}
		$now = time();

		$perc = (float)($done / $total);

		$bar = floor($perc * $size);

		$status_bar = "\r[";
		$status_bar .= str_repeat("=", $bar);
		if ($bar < $size) {
			$status_bar .= ">";
			$status_bar .= str_repeat(" ", $size - $bar);
		} else {
			$status_bar .= "=";
		}

		$disp = number_format($perc * 100, 0);

		$status_bar .= "] $disp%  $done/$total";

		$rate = ($now - $start_time) / $done;
		$left = $total - $done;
		$eta = round($rate * $left, 2);

		$elapsed = $now - $start_time;

		$status_bar .= " remaining: " . number_format($eta) . " sec.  elapsed: " . number_format($elapsed) . " sec.";

		echo "$status_bar  ";

		flush();

		// when done, send a newline
		if ($done == $total) {
			echo "\n\n\n\n";
		}
	}
}