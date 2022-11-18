<?php

/**
 * @category    Nooe
 * @package     Nooe_Connector
 * @author      NOOE Team <dev@nooestores.com>
 * @copyright   Copyright(c) 2022 NOOE (https://www.nooestores.com)
 * @license     https://opensource.org/licenses/gpl-3.0.html GNU General Public License (GPL 3.0)
 */

namespace Nooe\Connector\Model;

use Exception;
use Nooe\Connector\Api\OrderInterface;

class Order implements OrderInterface
{
	/**
	 * API request endpoint
	 */
	const API_REQUEST_ENDPOINT = 'orders';

	/**
	 * @var \Nooe\Connector\Helper\Data
	 */
	private $helperData;

	/**
	 * @var \Nooe\Connector\Model\Connector
	 */
	private $connector;

	/**
	 * @var \Nooe\Connector\Logger\Logger
	 */
	private $logger;

	/**
	 * @var \Magento\Store\Model\StoreManagerInterface
	 */
	protected $_storeManager;

	/**
	 * @var \Magento\Customer\Model\CustomerFactory
	 */
	protected $customerFactory;

	/**
	 * @var \Magento\Quote\Model\QuoteFactory
	 */
	protected $quote;

	/**
	 * @var \Magento\Customer\Api\CustomerRepositoryInterface
	 */
	protected $customerRepository;

	/**
	 * @var \Magento\Catalog\Model\Product
	 */
	protected $_product;

	/**
	 * @var \Magento\Quote\Model\QuoteManagement 
	 */
	protected $quoteManagement;

	/**
	 * @var \Magento\Sales\Model\Order\Status\HistoryFactory
	 */
	protected $orderHistoryFactory;

	/**
	 * @var \Magento\Quote\Model\Quote\Address\Rate
	 */
	protected $rate;

	/**
	 * @var \Nooe\Connector\Helper\Data
	 */
	protected $configData;


	/**
	 * Order constructor.
	 *
	 * @param \Nooe\Connector\Helper\Data $helperData
	 * @param \Nooe\Connector\Model\Connector $connector
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
	 * @param \Magento\Customer\Model\CustomerFactory $customerFactory
	 * @param \Magento\Quote\Model\QuoteFactory $quote
	 * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
	 * @param \Magento\Catalog\Model\Product $product
	 * @param \Magento\Quote\Model\QuoteManagement $quoteManagement
	 * @param \Magento\Quote\Model\Quote\Address\Rate $rate
	 * @param \Magento\Sales\Model\Order\Status\HistoryFactory
	 * @param \Nooe\Connector\Helper\Data $configData
	 * @param \Nooe\Connector\Logger\Logger $logger
	 */
	public function __construct(
		\Nooe\Connector\Helper\Data $helperData,
		\Nooe\Connector\Model\Connector $connector,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Customer\Model\CustomerFactory $customerFactory,
		\Magento\Quote\Model\QuoteFactory $quote,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
		\Magento\Catalog\Model\Product $product,
		\Magento\Quote\Model\QuoteManagement $quoteManagement,
		\Magento\Quote\Model\Quote\Address\Rate $rate,
		\Magento\Sales\Model\Order\Status\HistoryFactory $orderHistoryFactory,
		\Nooe\Connector\Helper\Data $configData,
		\Nooe\Connector\Logger\Logger $logger
	) {
		$this->helperData = $helperData;
		$this->connector = $connector;
		$this->_storeManager = $storeManager;
		$this->customerFactory = $customerFactory;
		$this->quote = $quote;
		$this->customerRepository = $customerRepository;
		$this->_product = $product;
		$this->quoteManagement = $quoteManagement;
		$this->rate = $rate;
		$this->orderHistoryFactory = $orderHistoryFactory;
		$this->configData = $configData;
		$this->logger = $logger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function create($orderData)
	{
		$store = $this->_storeManager->getStore();
		$storeId = $store->getId();
		$websiteId = $this->_storeManager->getStore()->getWebsiteId();
		$customer = $this->customerFactory->create();
		$customer->setWebsiteId($websiteId);
		$customer->loadByEmail($orderData['email']); // load customet by email address

		$guest = false;
		if (!$customer->getEntityId()) {
			$guest = true;
		}
		$quote = $this->quote->create(); //Create object of quote
		$quote->setStore($store); //set store for which you create quote
		$quote->setCurrency();

		if ($guest) {
			// Set Customer Data on Quote, Do not create customer.
			$quote->setCustomerFirstname($orderData['shipping_address']['firstname']);
			$quote->setCustomerLastname($orderData['shipping_address']['lastname']);
			$quote->setCustomerEmail($orderData['email']);
			$quote->setCustomerIsGuest(true);
		} else {
			// if you have allready buyer id then you can load customer directly
			$customer = $this->customerRepository->getById($customer->getEntityId());
			$quote->assignCustomer($customer); //Assign quote to customer
		}

		//add items in quote
		foreach ($orderData['items'] as $item) {
			$product = $this->_product->load($item['product_id']);
			$quote->addProduct(
				$product,
				intval($item['qty'])
			);
		}

		//Set Address to quote
		$quote->getBillingAddress()->addData($orderData['billing_address']);
		$quote->getShippingAddress()->addData($orderData['shipping_address']);

		// Collect Rates and Set Shipping & Payment Method
		$shippingRateCarrier = 'nooe_shipping';
		$shippingRateCarrierTitle = 'NOOE SHIPPING';
		$shippingRateCode = 'nooe_shipping';
		$shippingRateMethod = 'nooe_shipping';
		$shippingRatePrice = $orderData['shipping_amount'];
		$shippingRateMethodTitle = 'NOOE SHIPPING METHOD';

		$this->rate->setCarrier($shippingRateCarrier);
		$this->rate->setCarrierTitle($shippingRateCarrierTitle);
		$this->rate->setCode($shippingRateCode);
		$this->rate->setMethod($shippingRateMethod);
		$this->rate->setPrice($shippingRatePrice);
		$this->rate->setMethodTitle($shippingRateMethodTitle);
		$shippingAddress = $quote->getShippingAddress();
		$shippingAddress->setCollectShippingRates(true)
			->collectShippingRates()
			->setShippingMethod($shippingRateCode); //shipping method
		$quote->getShippingAddress()->addShippingRate($this->rate);

		$quote->setPaymentMethod('nooe_payments'); //payment method
		$quote->setInventoryProcessed(false); //not affect inventory
		$quote->save(); //Now Save quote and your quote is ready

		// Set Sales Order Payment
		$quote->getPayment()->importData(['method' => 'nooe_payments']);

		// Collect Totals & Save Quote
		$quote->collectTotals()->save();

		// Create Order From Quote
		$order = $this->quoteManagement->submit($quote);

		// Add order comment
		if ($orderData['comment']) {
			$history = $this->orderHistoryFactory->create()
				->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING) // Update status when passing $comment parameter
				->setEntityName(\Magento\Sales\Model\Order::ENTITY) // Set the entity name for order
				->setComment(
					__('Note: %1.', $orderData['comment'])
				);
			$history->setIsCustomerNotified(false) // Enable Notify your customers via email
				->setIsVisibleOnFront(false); // Enable order comment visible on sales order details
			$order->addStatusHistory($history);
		}

		$order->setEmailSent(1);
		if ($order->getEntityId()) {
			$prefix = (string)$this->configData->getOrderPrefix($storeId);
			$incrementId = trim($prefix) . $order->getIncrementId();
			$order->setIncrementId($incrementId);
			//TODO: valurare se settare la stessa data di NOOE o lasciare la data di inserimento (crea difficoltà nel trovare l'ordine dato l'increment sequenziale che pero non segue la data)
			//$order->setCreatedAt($orderData['order_date']);
			$success = $order->save();

			if ($success) {
				$result = ['success' => true, 'error' => false, 'message' => 'Order id: ' . $order->getRealOrderId() . ' created'];
			}
		} else {
			$result = ['success' => false, 'error' => true, 'message' => 'Error in order creation'];
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getList($incrementId = null)
	{
		$searchCriteria = array();
		$orderLimit     = 100;
		$startDate      = $this->helperData->getStartDate();
		$orderId		= $this->helperData->getOrderId();
		$storeCode      = $this->helperData->getStoreCode();

		$suckerInterval = ' +15 day';
		$fromDate       = date('Y-m-d H:i:s', strtotime($startDate));
		$toDate         = date('Y-m-d H:i:s', strtotime($startDate . $suckerInterval));

		if ($startDate) {

			if (!is_null($incrementId)) {
				$searchCriteria[] = 'searchCriteria[filter_groups][0][filters][0][field]=store_code&';
				$searchCriteria[] = 'searchCriteria[filter_groups][0][filters][0][condition_type]=eq&';
				$searchCriteria[] = 'searchCriteria[filter_groups][0][filters][0][value]=' . $storeCode . '&';
				$searchCriteria[] = 'searchCriteria[filter_groups][1][filters][0][field]=increment_id&';
				$searchCriteria[] = 'searchCriteria[filter_groups][1][filters][0][condition_type]=eq&';
				$searchCriteria[] = 'searchCriteria[filter_groups][1][filters][0][value]=' . $incrementId . '&';
			} else {
				$searchCriteria[] = 'searchCriteria[filter_groups][0][filters][0][field]=store_code&';
				$searchCriteria[] = 'searchCriteria[filter_groups][0][filters][0][condition_type]=eq&';
				$searchCriteria[] = 'searchCriteria[filter_groups][0][filters][0][value]=' . $storeCode . '&';
				$searchCriteria[] = 'searchCriteria[filter_groups][1][filters][0][field]=entity_id&';
				$searchCriteria[] = 'searchCriteria[filter_groups][1][filters][0][condition_type]=gt&';
				$searchCriteria[] = 'searchCriteria[filter_groups][1][filters][0][value]=' . $orderId . '&';
				$searchCriteria[] = 'searchCriteria[filter_groups][2][filters][0][field]=created_at&';
				$searchCriteria[] = 'searchCriteria[filter_groups][2][filters][0][condition_type]=gteq&';
				$searchCriteria[] = 'searchCriteria[filter_groups][2][filters][0][value]=' . $fromDate . '&';
				$searchCriteria[] = 'searchCriteria[filter_groups][3][filters][0][field]=created_at&';
				$searchCriteria[] = 'searchCriteria[filter_groups][3][filters][0][condition_type]=lteq&';
				$searchCriteria[] = 'searchCriteria[filter_groups][3][filters][0][value]=' . $toDate . '&';
				$searchCriteria[] = 'searchCriteria[sortOrders][0][field]=entity_id&';
				$searchCriteria[] = 'searchCriteria[sortOrders][0][direction]=ASC&';
			}

			$searchCriteria[] = 'searchCriteria[pageSize]=' . $orderLimit . '&';
			$searchCriteria[] = 'searchCriteria[currentPage]=1';

			try {
				$endpoint = self::API_REQUEST_ENDPOINT . '/?' . implode('', $searchCriteria);
				$allOrders = $this->connector->doRequest($endpoint);

				if ($allOrders && isset($allOrders->items) && count($allOrders->items)) {
					return $allOrders->items;
				}
			} catch (Exception $e) {
				$this->logger->error($e->getMessage());
				throw new Exception($e->getMessage());
			}
		} else {
			throw new Exception("Missing Start Date in module configuration");
		}
	}
}