<?php

/**
 * @category    Nooe
 * @package     Nooe_Connector
 * @author      NOOE Team <dev@nooestores.com>
 * @copyright   Copyright(c) 2022 NOOE (https://www.nooestores.com)
 * @license     https://opensource.org/licenses/gpl-3.0.html GNU General Public License (GPL 3.0)
 */

declare(strict_types=1);

namespace Nooe\Connector\Service;

use Exception;

class OrderService
{
	/**
	 * @var \Nooe\Connector\Model\Order
	 */
	private $order;

	/**
	 * @var Nooe\Connector\Model\Product
	 */
	private $product;

	/**
	 * @var \Magento\Catalog\Model\ProductFactory $productFactory
	 */
	protected $productFactory;

	/**
	 * @var \Nooe\Connector\Helper\Data $configData
	 */
	protected $configData;

	/**
	 * @var \Nooe\Connector\Logger\Logger $logger
	 */
	private $logger;

	/**
	 * @var \Nooe\Connector\Helper\Sync
	 */
	private $syncHelper;

	/**
	 * @var \Magento\Directory\Model\RegionFactory $regionFactory
	 */
	protected $regionFactory;


	/**
	 * OrderService constructor.
	 *
	 * @param \Nooe\Connector\Model\Order $order
	 * @param \Nooe\Connector\Model\Product $product
	 * @param \Magento\Catalog\Model\ProductFactory $productFactory
	 * @param \Nooe\Connector\Helper\Data $configData
	 * @param \Nooe\Connector\Logger\Logger $logger
	 * @param \Nooe\Connector\Helper\Sync $syncHelper
	 * @param \Magento\Directory\Model\RegionFactory $regionFactory
	 */
	public function __construct(
		\Nooe\Connector\Model\Order $order,
		\Nooe\Connector\Model\Product $product,
		\Magento\Catalog\Model\ProductFactory $productFactory,
		\Nooe\Connector\Helper\Data $configData,
		\Nooe\Connector\Logger\Logger $logger,
		\Nooe\Connector\Helper\Sync $syncHelper,
		\Magento\Directory\Model\RegionFactory $regionFactory
	) {
		$this->order = $order;
		$this->product = $product;
		$this->productFactory = $productFactory;
		$this->configData = $configData;
		$this->logger = $logger;
		$this->syncHelper = $syncHelper;
		$this->regionFactory = $regionFactory;
	}

	public function getRegionId($stateCode, $countryId)
	{
		$regionFactory = $this->regionFactory->create();
		return $regionFactory->loadByCode($stateCode, $countryId)->getRegionId();
	}


	/**
	 * Synchronizes the list of orders from a remote Magento store.
	 * If $incrementid was passed as an argument, it will only sync the order corresponding to that increment id.
	 *
	 * @param string|null $incrementId
	 * @return void
	 * @throws \Exception
	 */
	public function sync($incrementId = null)
	{
		// get orders from remote Magento
		$orders = $this->order->getList($incrementId);
		$totalOrderCount = count((array)$orders);
		$count = 0;

		if ($totalOrderCount) {
			foreach ($orders as $key => $order) {
				echo $order->increment_id . ' (' . $order->status . ")";
				$count++;
				$this->syncHelper->show_status($count, $totalOrderCount, 30);

				try {
					$items = array();

					foreach ($order->items as $item) {
						if ($item->product_type == 'simple') {
							$product = $this->productFactory->create();

							// check if product exist
							$productId = $product->getIdBySku($item->sku);

							if ($productId) {
								$stockItem = $this->product->getStockBySku($item->sku);

								if (!empty($stockItem)) {
									if (!$stockItem['is_in_stock'] || $stockItem['qty'] < $item->qty_ordered) {
										$errorMessage = "ORDER #" . $order->increment_id . " - Quantity not available or out of stock for the SKU " . $item->sku;
										$this->logger->error($errorMessage);
										throw new Exception($errorMessage);
									} else {

										$qty = $item->qty_ordered;
										$price = $item->row_total_incl_tax;

										if (isset($item->parent_item)) {
											$qty = $item->parent_item->qty_ordered;
											$price = $item->parent_item->row_total_incl_tax;
										}

										$cartItem['product_id'] = $productId;
										$cartItem['qty'] = $qty;
										$cartItem['price'] = $price;
										$items[] = $cartItem;
									}
								} else {
									$errorMessage = "ORDER #" . $order->increment_id . " - Unable to verify quantity for SKU " . $item->sku;
									$this->logger->error($errorMessage);
									throw new Exception($errorMessage);
								}
							} else {
								$errorMessage = "ORDER #" . $order->increment_id . " - SKU " . $item->sku . " not exist";
								$this->logger->error($errorMessage);
								throw new Exception($errorMessage);
							}
						}
					}

					$billingAddress = $order->billing_address;
					$shippingAddress = $order->extension_attributes->shipping_assignments[0]->shipping->address;

					$giftMessage = ['from' => '', 'to' => '', 'message' => ''];
					if (isset($order->extension_attributes->gift_message)) {
						$giftMessage['from'] = $order->extension_attributes->gift_message['sender'];
						$giftMessage['to'] = $order->extension_attributes->gift_message['recipient'];
						$giftMessage['message'] = $order->extension_attributes->gift_message['message'];
					}

					$orderComment = '';
					$checkoutCommentLabel = '[CHECKOUT COMMENT] ';
					foreach ($order->status_histories as $history) {
						$comment = is_null($history->comment) ? '' : $history->comment;
						if (strpos($comment, $checkoutCommentLabel) !== false) {
							$orderComment = str_replace($checkoutCommentLabel, '', $comment);
							break;
						}
					}

					$localOrder = [
						'currency_id'		=> $order->order_currency_code,
						'email'				=> $order->customer_email,
						'increment_id'		=> $order->increment_id,
						'order_id'			=> $order->entity_id,
						'order_date'		=> $order->created_at,
						'items'				=> $items,
						'shipping_amount'	=> (float)$order->shipping_incl_tax,
						'gift_message'		=> $giftMessage,
						'comment'			=> $orderComment,
						'billing_address'	=> [
							'prefix'				=> isset($billingAddress->prefix) ? $billingAddress->prefix : '',
							'firstname'				=> isset($billingAddress->firstname) ? $billingAddress->firstname : '',
							'middlename'			=> isset($billingAddress->middlename) ? $billingAddress->middlename : '',
							'lastname'				=> isset($billingAddress->lastname) ? $billingAddress->lastname : '',
							'suffix'				=> isset($billingAddress->suffix) ? $billingAddress->suffix : '',
							'company'				=> isset($billingAddress->company) ? $billingAddress->company : '',
							'street'				=> isset($billingAddress->street[0]) ? $billingAddress->street[0] : '',
							'country_id'			=> isset($billingAddress->country_id) ? $billingAddress->country_id : '',
							'region_id'				=> isset($billingAddress->region_code) ? $this->getRegionId($billingAddress->region_code, $billingAddress->country_id) : 0,
							'city'					=> isset($billingAddress->city) ? $billingAddress->city : '',
							'postcode'				=> isset($billingAddress->postcode) ? $billingAddress->postcode : '',
							'telephone'				=> isset($billingAddress->telephone) ? $billingAddress->telephone : '',
							'fax'					=> isset($billingAddress->fax) ? $billingAddress->fax : '',
							'vat_id'				=> isset($billingAddress->vat_id) ? $billingAddress->vat_id : '',
							'save_in_address_book'	=> 0
						],
						'shipping_address'	=> [
							'prefix'				=> isset($shippingAddress->prefix) ? $shippingAddress->prefix : '',
							'firstname'				=> isset($shippingAddress->firstname) ? $shippingAddress->firstname : '',
							'middlename'			=> isset($shippingAddress->middlename) ? $shippingAddress->middlename : '',
							'lastname'				=> isset($shippingAddress->lastname) ? $shippingAddress->lastname : '',
							'suffix'				=> isset($shippingAddress->suffix) ? $shippingAddress->suffix : '',
							'company'				=> isset($shippingAddress->company) ? $shippingAddress->company : '',
							'street'				=> isset($shippingAddress->street[0]) ? $shippingAddress->street[0] : '',
							'country_id'			=> isset($shippingAddress->country_id) ? $shippingAddress->country_id : '',
							'region_id'				=> isset($shippingAddress->region_code) ? $this->getRegionId($shippingAddress->region_code, $shippingAddress->country_id) : 0,
							'city'					=> isset($shippingAddress->city) ? $shippingAddress->city : '',
							'postcode'				=> isset($shippingAddress->postcode) ? $shippingAddress->postcode : '',
							'telephone'				=> isset($shippingAddress->telephone) ? $shippingAddress->telephone : '',
							'fax'					=> isset($shippingAddress->fax) ? $shippingAddress->fax : '',
							'vat_id'				=> isset($shippingAddress->vat_id) ? $shippingAddress->vat_id : '',
							'save_in_address_book'	=> 0
						]
					];

					$result = $this->order->create($localOrder);

					if ($result['success']) {
						$this->configData->setStartDate($localOrder['order_date']);
						$this->configData->setIncrementId($localOrder['increment_id']);
						$this->configData->setOrderId($localOrder['order_id']);
						$this->logger->info($result['message']);
					} else {
						$this->logger->error("ORDER #" . $order->increment_id . " - " . $result['message']);
						throw new Exception($result['message']);
					}
				} catch (Exception $e) {
					$this->logger->error("ORDER #" . $order->increment_id . " - " . $e->getMessage());
					throw new Exception($e->getMessage());
				}
			}
		}
	}
}