<?php

/**
 * @category    Nooe
 * @package     Nooe_Connector
 * @author      NOOE Team <dev@nooestores.com>
 * @copyright   Copyright(c) 2022 NOOE (https://www.nooestores.com)
 * @license     https://opensource.org/licenses/gpl-3.0.html GNU General Public License (GPL 3.0)
 */

namespace Nooe\Connector\Console\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Exception\InvalidArgumentException;


class Sync extends Command
{
	/**
	 * @var \Magento\Store\Model\StoreManagerInterface
	 */
	protected $_storeManagerInterface;

	/**
	 * @var \Magento\Customer\Api\Data\CustomerInterfaceFactory
	 */
	protected $_customerInterfaceFactory;

	/**
	 * @var \Magento\Framework\Encryption\EncryptorInterface
	 */
	protected $_encryptorInterface;

	/**
	 * @var \Magento\Customer\Api\CustomerRepositoryInterface
	 */
	protected $_customerRepositoryInterface;

	/**
	 * @var \Magento\Customer\Model\CustomerFactory
	 */
	protected $_customerFactory;

	/**
	 * @var \Magento\Customer\Api\AddressRepositoryInterface
	 */
	protected $_addressRepository;

	/**
	 * @var \Magento\Customer\Api\Data\AddressInterfaceFactory
	 */
	protected $_addressDataFactory;

	/**
	 * @var \Magento\Framework\Api\SearchCriteriaBuilder
	 */
	protected $_searchCriteriaBuilder;

	/**
	 * @var \Magento\Framework\Api\FilterBuilder
	 */
	protected $_filterBuilder;

	/**
	 * @var \Magento\Framework\App\State
	 */
	private $state;

	/**
	 * @var \Magento\Framework\Registry
	 */
	private $registry;

	/**
	 * @var \Nooe\Connector\Service\OrderService
	 */
	private $orderService;

	/**
	 * @var \Nooe\Connector\Service\ProductService
	 */
	private $productService;

	/**
	 * @var \Nooe\Connector\Helper\Sync
	 */
	private $syncHelper;

	/**
	 * @var \Nooe\Connector\Helper\Data $configData
	 */
	private $configData;

	/**
	 * Sync constructor.
	 *
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManagerInterface
	 * @param \Magento\Customer\Api\Data\CustomerInterfaceFactory $customerInterfaceFactory
	 * @param \Magento\Framework\Encryption\EncryptorInterface $encryptorInterface
	 * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface
	 * @param \Magento\Customer\Model\CustomerFactory $customerFactory
	 * @param \Magento\Customer\Api\AddressRepositoryInterface $addressRepository
	 * @param \Magento\Customer\Api\Data\AddressInterfaceFactory $addressDataFactory
	 * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
	 * @param \Magento\Framework\Api\FilterBuilder $filterBuilder
	 * @param \Magento\Framework\App\State $state
	 * @param \Magento\Framework\Registry $registry
	 * @param \Nooe\Connector\Service\OrderService $orderService
	 * @param \Nooe\Connector\Service\ProductService $productService
	 * @param \Nooe\Connector\Helper\Sync $syncHelper
	 * @param \Nooe\Connector\Helper\Data $configData
	 */
	public function __construct(
		\Magento\Store\Model\StoreManagerInterface $storeManagerInterface,
		\Magento\Customer\Api\Data\CustomerInterfaceFactory $customerInterfaceFactory,
		\Magento\Framework\Encryption\EncryptorInterface $encryptorInterface,
		\Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface,
		\Magento\Customer\Model\CustomerFactory $customerFactory,
		\Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
		\Magento\Customer\Api\Data\AddressInterfaceFactory $addressDataFactory,
		\Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
		\Magento\Framework\Api\FilterBuilder $filterBuilder,
		\Magento\Framework\App\State $state,
		\Magento\Framework\Registry $registry,
		\Nooe\Connector\Service\OrderService $orderService,
		\Nooe\Connector\Service\ProductService $productService,
		\Nooe\Connector\Helper\Sync $syncHelper,
		\Nooe\Connector\Helper\Data $configData
	) {
		$registry->register('isSecureArea', true);

		$this->_storeManagerInterface = $storeManagerInterface;
		$this->_customerInterfaceFactory = $customerInterfaceFactory;
		$this->_encryptorInterface = $encryptorInterface;
		$this->_customerRepositoryInterface = $customerRepositoryInterface;
		$this->_customerFactory = $customerFactory;
		$this->_addressRepository = $addressRepository;
		$this->_addressDataFactory = $addressDataFactory;
		$this->_searchCriteriaBuilder = $searchCriteriaBuilder;
		$this->_filterBuilder = $filterBuilder;
		$this->state = $state;
		$this->registry = $registry;
		$this->orderService = $orderService;
		$this->productService = $productService;
		$this->syncHelper = $syncHelper;
		$this->configData = $configData;
		parent::__construct();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this->setName('nooe:sync')
			->setDescription('Sync')
			->addOption('action', "action", InputOption::VALUE_OPTIONAL, "Specific Action")
			->addOption('increment', "increment", InputOption::VALUE_OPTIONAL, "Specific Increment Id");
		parent::configure();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->state->setAreaCode('adminhtml');
		$output->writeln($this->syncHelper->printHeading());
		$action = null;
		$increment = null;

		try {
			if ($input->getOption('action')) {
				$action = $input->getOption('action');
			}

			if ($input->getOption('increment')) {
				$increment = $input->getOption('increment');
			}


			try {
				switch ($action) {
					case 'order_reset':
						$this->configData->setStartDate('2022-09-01 00:00:00');
						$this->configData->setIncrementId(0);
						$this->configData->setOrderId(0);
						break;
					case 'product':
						$this->productService->sync();
						break;
					default:
						$this->orderService->sync($increment);
						break;
				}
			} catch (Exception $e) {
				$output->writeln($e->getMessage());
			}
		} catch (InvalidArgumentException $e) {
			$output->writeln('<error>Invalid argument.</error>');
		}

		return \Magento\Framework\Console\Cli::RETURN_SUCCESS;
	}
}