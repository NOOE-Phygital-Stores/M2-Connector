<?php

/**
 * @category    Nooe
 * @package     Nooe_Connector
 * @author      NOOE Team <dev@nooestores.com>
 * @copyright   Copyright(c) 2022 NOOE (https://www.nooestores.com)
 * @license     https://opensource.org/licenses/gpl-3.0.html GNU General Public License (GPL 3.0)
 */

namespace Nooe\Connector\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
	/**
	 * XML path for Access Token.
	 */
	const ACCESS_TOKEN = 'nooe_connector/settings/access_token';

	/**
	 * XML path for Store Code.
	 */
	const STORE_CODE = 'nooe_connector/settings/store_code';

	/**
	 * XML path for Store Id.
	 */
	const STORE_ID = 'nooe_connector/settings/store_id';

	/**
	 * XML path for Start Date. Defines the start date for synchronization.
	 */
	const START_DATE = 'nooe_connector/settings/start_date';

	/**
	 * XML path for order Increment ID. Contains the increment id of the last synchronized order.
	 */
	const INCREMENT_ID = 'nooe_connector/settings/increment_id';

	/**
	 * XML path for Order ID. Contains the ID of the last synchronized order.
	 */
	const ORDER_ID = 'nooe_connector/settings/order_id';

	/**
	 * XML path for Order Prefix.
	 * Defines a prefix for 'increment_id' with which orders will be saved during synchronization.
	 */
	const ORDER_PREFIX = 'nooe_connector/settings/order_prefix';

	/**
	 * XML path for Product SKU.
	 * Defines a product SKU for stock synchronization.
	 */
	const PRODUCT_SKU = 'nooe_connector/settings/product_sku';

	/**
	 * XML path for Salable Quantity.
	 * Get if exist Salable Quantity instead default quantity.
	 */
	const SALABLE_QUANTITY = 'nooe_connector/settings/salable_quantity';

	/**
	 * XML path for Debug Mode.
	 * Enable the debug mode.
	 */
	const DEBUG_MODE = 'nooe_connector/settings/debug_mode';

	/**
	 * @var \Magento\Framework\App\Config\Storage\WriterInterface
	 */
	private $_configWriter;

	/**
	 * @var \Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory
	 */
	protected $scopeCollectionFactory;


	/**
	 * Data constructor.
	 *
	 * @param \Magento\Framework\App\Helper\Context $context
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
	 * @param \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
	 */
	public function __construct(
		\Magento\Framework\App\Helper\Context $context,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
		\Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
		\Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory $scopeCollectionFactory
	) {
		$this->_storeManager = $storeManager;
		$this->_configWriter = $configWriter;
		$this->scopeCollectionFactory = $scopeCollectionFactory;
		parent::__construct($context);
	}

	/**
	 * Retrieve module config value.
	 * @param string $config_path
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $store
	 * @return mixed
	 */
	private function getConfig($config_path, $store = null)
	{
		switch ($config_path) {
			case self::START_DATE:
			case self::INCREMENT_ID:
			case self::ORDER_ID:
				$storeId = $store ? $store : 0;
				$config = $this->scopeCollectionFactory->create();
				$result = $config->addFieldToFilter('path', ['eq' => $config_path])->addFieldToFilter('scope', ['eq' => $storeId])->getFirstItem()->getValue();
				break;

			default:
				// Get cached config values
				$store = $this->_storeManager->getStore($store);
				$result = $this->scopeConfig->getValue($config_path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $store);
				break;
		}

		return $result;
	}

	/**
	 * Save module config value to storage.
	 *
	 * @param string $config_path
	 * @param string $value
	 * @param int $storeId
	 * @return void
	 */
	private function writeConfig($config_path, $value, $storeId = 0)
	{
		$this->_configWriter->save($config_path, $value, \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $storeId);
	}

	/**
	 * Returns access token for authorization bearer.
	 *
	 * @param null|string|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function getAccessToken($storeId = null)
	{
		return $this->getConfig(self::ACCESS_TOKEN, $storeId);
	}

	/**
	 * Returns store code configuration value.
	 *
	 * @param null|string|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function getStoreCode($storeId = null)
	{
		return $this->getConfig(self::STORE_CODE, $storeId);
	}

	/**
	 * Returns store code configuration value.
	 *
	 * @param null|string|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function getStoreId($storeId = null)
	{
		return $this->getConfig(self::STORE_ID, $storeId);
	}

	/**
	 * Returns start date for synchronization.
	 *
	 * @param null|string|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function getStartDate($storeId = null)
	{
		return $this->getConfig(self::START_DATE, $storeId);
	}

	/**
	 * Returns last synchronized order increment ID.
	 *
	 * @param null|string|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function getIncrementId($storeId = null)
	{
		return $this->getConfig(self::INCREMENT_ID, $storeId);
	}

	/**
	 * Returns last synchronized order ID.
	 *
	 * @param null|string|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function getOrderId($storeId = null)
	{
		return $this->getConfig(self::ORDER_ID, $storeId);
	}

	/**
	 * Returns order prefix configuration value.
	 *
	 * @param null|string|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function getOrderPrefix($storeId = null)
	{
		return $this->getConfig(self::ORDER_PREFIX, $storeId);
	}

	/**
	 * Returns product sku configuration value.
	 *
	 * @param null|string|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function getProductSku($storeId = null)
	{
		return $this->getConfig(self::PRODUCT_SKU, $storeId);
	}

	/**
	 * Returns salable quantity configuration value.
	 *
	 * @param null|string|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function getSalableQuantity($storeId = null)
	{
		return (bool)$this->getConfig(self::SALABLE_QUANTITY, $storeId);
	}

	/**
	 * Returns debug mode configuration value.
	 *
	 * @param null|string|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function getDebugMode($storeId = null)
	{
		return (bool)$this->getConfig(self::DEBUG_MODE, $storeId);
	}

	/**
	 * Saves access token for authorization bearer into module config value.
	 * @param string $value
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function setAccessToken($value, $storeId = 0)
	{
		return $this->writeConfig(self::ACCESS_TOKEN, $value, $storeId);
	}

	/**
	 * Saves X into module config value.
	 * @param string $value
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function setStoreCode($value, $storeId = 0)
	{
		return $this->writeConfig(self::STORE_CODE, $value, $storeId);
	}

	/**
	 * Saves X into module config value.
	 * @param string $value
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function setStoreId($value, $storeId = 0)
	{
		return $this->writeConfig(self::STORE_ID, $value, $storeId);
	}

	/**
	 * Saves synchronization start date into module config value.
	 * @param string $value
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function setStartDate($value, $storeId = 0)
	{
		return $this->writeConfig(self::START_DATE, $value, $storeId);
	}

	/**
	 * Saves last synchronized order increment ID into module config value.
	 * @param string $value
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function setIncrementId($value, $storeId = 0)
	{
		return $this->writeConfig(self::INCREMENT_ID, $value, $storeId);
	}

	/**
	 * Saves last synchronized order ID into module config value.
	 * @param string $value
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function setOrderId($value, $storeId = 0)
	{
		return $this->writeConfig(self::ORDER_ID, $value, $storeId);
	}

	/**
	 * Saves order prefix into module config value.
	 * @param string $value
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function setOrderPrefix($value, $storeId = 0)
	{
		return $this->writeConfig(self::ORDER_PREFIX, $value, $storeId);
	}

	/**
	 * Saves product sku into module config value.
	 * @param string $value
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function setProductSku($value, $storeId = 0)
	{
		return $this->writeConfig(self::PRODUCT_SKU, $value, $storeId);
	}

	/**
	 * Saves salable quantity config value.
	 * @param bool|int $value
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function setSalableQuantity($storeId = null)
	{
		return $this->writeConfig(self::SALABLE_QUANTITY, $value, $storeId);
	}

	/**
	 * Saves debug mode config value.
	 * @param bool|int $value
	 * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
	 */
	public function setDebugMode($storeId = null)
	{
		return $this->writeConfig(self::DEBUG_MODE, $value, $storeId);
	}
}