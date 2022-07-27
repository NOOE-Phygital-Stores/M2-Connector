<?php

namespace Nooe\M2Connector\Model\Shipping;

use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;

class NooeShipping extends AbstractCarrier implements CarrierInterface
{

    /**
     * @var string
     */
    protected $_code = 'nooe_shipping';
    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_rateMethodFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Check subtotal for allowed free shipping
     *
     * @param RateRequest $request
     *
     * @return bool
     */
    private function isFreeShippingRequired(RateRequest $request): bool
    {
        $minSubtotal = $request->getPackageValueWithDiscount();
        if ($request->getBaseSubtotalWithDiscountInclTax()
            && $this->getConfigFlag('tax_including')) {
            $minSubtotal = $request->getBaseSubtotalWithDiscountInclTax();
        }

        return $minSubtotal >= $this->getConfigData('free_shipping_subtotal');
    }

    /**
     * FreeShipping Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();

        $this->_updateFreeMethodQuote($request);

        if ($request->getFreeShipping() || $this->isFreeShippingRequired($request)) {
            /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
            $method = $this->_rateMethodFactory->create();

            $method->setCarrier('nooe_shipping');
            $method->setCarrierTitle($this->getConfigData('title'));

            $method->setMethod('nooe_shipping');
            $method->setMethodTitle($this->getConfigData('name'));

            $method->setPrice('0.00');
            $method->setCost('0.00');

            $result->append($method);
        } elseif ($this->getConfigData('showmethod')) {
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $errorMsg = $this->getConfigData('specificerrmsg');
            $error->setErrorMessage(
                $errorMsg ? $errorMsg : __(
                    'Sorry, but we can\'t deliver to the destination country with this shipping module.'
                )
            );
            return $error;
        }

        return $result;
    }

    /**
     * Allows free shipping when all product items have free shipping (promotions etc.)
     *
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return void
     */
    protected function _updateFreeMethodQuote($request)
    {
        $freeShipping = false;
        $items = $request->getAllItems();
        $c = count($items);
        for ($i = 0; $i < $c; $i++) {
            if ($items[$i]->getProduct() instanceof \Magento\Catalog\Model\Product) {
                if ($items[$i]->getFreeShipping()) {
                    $freeShipping = true;
                } else {
                    return;
                }
            }
        }
        if ($freeShipping) {
            $request->setFreeShipping(true);
        }
    }


    /**
     * Returns allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

}