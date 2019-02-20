<?php
/**
 * Ebizmarts_Abandonedcart Magento JS component
 *
 * @category    Ebizmarts
 * @package     Ebizmarts_Abandonedcart
 * @author      Ebizmarts Team <info@ebizmarts.com>
 * @copyright   Ebizmarts (http://ebizmarts.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


namespace Ebizmarts\AbandonedCart\Model;

use Ebizmarts\AbandonedCart\Model\Sales\Quote;

class Cron
{
    protected $days;
    protected $maxtimes;
    protected $sendcoupon;
    protected $firstdate;
    protected $unit;
    protected $customergroups;
    protected $couponamount;
    protected $couponexpiredays;
    protected $coupontype;
    protected $couponlength;
    protected $couponlabel;
    protected $sendcoupondays;

    /**
     * @var \Magento\Store\Model\StoreManager
     */
    protected $_storeManager;
    /**
     * @var \Ebizmarts\AbandonedCart\Helper\Data
     */
    protected $_helper;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $_stockRegistry;
    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $_transportBuilder;


    /**
     * @var Quote
     */
    protected $_quoteFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @param \Magento\Store\Model\StoreManager $storeManager
     * @param \Ebizmarts\AbandonedCart\Helper\Data $helper
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Store\Model\StoreManager $storeManager,
        \Ebizmarts\AbandonedCart\Helper\Data $helper,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Ebizmarts\AbandonedCart\Model\Sales\QuoteFactory $quote,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->_storeManager    = $storeManager;
        $this->_helper          = $helper;
        $this->_objectManager   = $objectManager;
        $this->_transportBuilder= $transportBuilder;

        $this->_quoteFactory   = $quote;

        $this->_stockRegistry   = $stockRegistry;

        $this->_logger          = $logger;
    }

    public function abandoned()
    {

        foreach($this->_storeManager->getStores() as $storeId => $val)
        {
            $this->_storeManager->setCurrentStore($storeId);

            if($this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::ACTIVE)) {
                $this->_proccess($storeId);
            }
        }
    }

    public function cleanAbandonedCartExpiredCoupons()
    {
        $allStores = $this->_storeManager->getStores();
        foreach ($allStores as $storeId => $val) {
            if ($this->_helper->getConfig(Ebizmarts_AbandonedCart_Model_Config::ACTIVE, $storeId)) {
                $this->_cleanCoupons($storeId);
            }
        }
    }

    /**
     * @param $storeId
     */
    protected function _proccess($storeId)
    {

        $this->days = array(
            0 => $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::DAYS_1, $storeId),
            1 => $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::DAYS_2, $storeId),
            2 => $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::DAYS_3, $storeId),
            3 => $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::DAYS_4, $storeId),
            4 => $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::DAYS_5, $storeId)
        );
        $this->maxtimes         = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::MAXTIMES, $storeId) + 1;
        $this->sendcoupon       = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::SEND_COUPON, $storeId);
        $this->firstdate        = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::FIRST_DATE, $storeId);

        if(empty($this->firstdate)) {
            // set the top date of the carts to get
            $expr = sprintf('DATE_SUB(now(), %s)', $this->_getIntervalUnitSql(90, 'DAY'));
            $this->firstdate = new \Zend_Db_Expr($expr);
        }




        $this->unit             = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::UNIT, $storeId);
//        $this->customergroups   = explode(",", $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::CUSTOMER_GROUPS, $storeId));
        if($this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::CUSTOMER_GROUPS, $storeId))
        {
            $this->customergroups   = explode(",", $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::CUSTOMER_GROUPS, $storeId));
        }
        else {
            $this->customergroups   = array();;
        }
        //coupon vars
        $this->couponamount = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::COUPON_AMOUNT, $storeId);
        $this->couponexpiredays = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::COUPON_EXPIRE, $storeId);
        $this->coupontype = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::COUPON_TYPE, $storeId);
        $this->couponlength = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::COUPON_LENGTH, $storeId);
        $this->couponlabel = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::COUPON_LABEL, $storeId);




        // iterates one time for each mail number
        for ($run = $this->maxtimes-1; $run >=0; $run--) {

            if (!$this->days[$run]) {
                return;
            }
            $this->_processRun($run, $storeId);
        }
    }
    protected function _processRun($run, $storeId)
    {
        // subtract days from latest run to get difference from the actual abandon date of the cart
        $diff = $this->days[$run];
        if ($run == 1 && $this->unit == \Ebizmarts\AbandonedCart\Model\Config::IN_HOURS) {
            $diff -= $this->days[0] / 24;
        } elseif ($run != 0) {
            $diff -= $this->days[$run - 1];
        }

        // set the top date of the carts to get
        $expr = sprintf('DATE_SUB(now(), %s)', $this->_getIntervalUnitSql($diff, 'DAY'));
        if ($run == 0 && $this->unit == \Ebizmarts\AbandonedCart\Model\Config::IN_HOURS) {
            $expr = sprintf('DATE_SUB(now(), %s)', $this->_getIntervalUnitSql($diff, 'HOUR'));
        }
        $from = new \Zend_Db_Expr($expr);

        // get collection of abandoned carts with cart_counter == $run
        $collection = $this->_objectManager->create('Magento\Reports\Model\ResourceModel\Quote\Collection');
        $collection->addFieldToFilter('items_count', array('neq' => '0'))
            ->addFieldToFilter('main_table.is_active', '1')
            ->addFieldToFilter('main_table.store_id', array('eq' => $storeId))
            ->addSubtotal($storeId)
            ->setOrder('updated_at');

        
        $collection->addFieldToFilter('main_table.converted_at', array(array('null' => true), $this->_getSuggestedZeroDate()))
            ->addFieldToFilter('main_table.updated_at', array('to' => $from, 'from' => $this->firstdate));
            //->addFieldToFilter('main_table.ebizmarts_abandonedcart_counter', array('eq' => $run));


        if(!$run) {
            $collection->getSelect()->joinLeft(
                ['abp' => 'abandonedcart_popup'],
                'abp.quote_id=main_table.entity_id',
                []
            );
            $collection->addFieldToFilter(['abp.id','abp.id'], [['null' => true], ['eq' => 0]]);
        } else {
            $collection->getSelect()->join(
                ['abp' => 'abandonedcart_popup'],
                'abp.quote_id=main_table.entity_id AND abp.counter='.$run,
                []
            );
        }


        $collection->addFieldToFilter('main_table.customer_email', array('neq' => ''));
        if (count($this->customergroups)) {
            $collection->addFieldToFilter('main_table.customer_group_id', array('in' => $this->customergroups));
        }

        //echo $collection->getSelect();

        // for each cart of the current run
        foreach ($collection as $quote) {

            $this->sendcoupondays = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::COUPON_DAYS, $storeId);

            $this->_proccessCollection($quote, $storeId);

            if (count($quote->getAllVisibleItems()) < 1) {
//                $quote2 = $this->_objectManager->create('\Magento\Quote\Model\Quote')->loadByIdWithoutStore($quote->getId());
//                $quote2->setEbizmartsAbandonedcartCounter($quote2->getEbizmartsAbandonedcartCounter() + 1);
//                $quote2->save();

                $quote = $this->_quoteFactory->create();
                $quote->loadByIdWithoutStore($quote->getId());
                $quote->setEbizmartsAbandonedcartCounter($quote->getEbizmartsAbandonedcartCounter() + 1);
                $quote->save();

                continue;
            }
            // check if they are any order from the customer with date >=
            //$collection2 = Mage::getResourceModel('reports/quote_collection');
            $collection2 = $this->_objectManager->create('\Magento\Reports\Model\ResourceModel\Quote\Collection');
            $collection2->addFieldToFilter('main_table.is_active', '0')
                ->addFieldToFilter('main_table.reserved_order_id', array('neq' => 'NULL'))
                ->addFieldToFilter('main_table.customer_email', array('eq' => $quote->getCustomerEmail()))
                ->addFieldToFilter('main_table.updated_at', array('from' => $quote->getUpdatedAt()));
            if ($collection2->getSize()) {
                continue;
            }
            $token = md5(rand(0, 9999999));

            $url = $this->_storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK) . 'abandonedcart/abandoned/loadquote?id=' . $quote->getEntityId() . '&token=' . $token;

            $data = array('AbandonedURL' => $url, 'AbandonedDate' => $quote->getUpdatedAt());

            // send email
            $senderid = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::SENDER, $storeId);
            $sender = array('name' => $this->_helper->getConfig("trans_email/ident_$senderid/name", $storeId),
                'email' => $this->_helper->getConfig("trans_email/ident_$senderid/email", $storeId));

            $email = $quote->getCustomerEmail();

            $name = $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname();

            //$quote2 = $this->_objectManager->create('\Magento\Quote\Model\Quote')->loadByIdWithoutStore($quote->getId());
            $quote = $this->_quoteFactory->create();
            $quote->loadByIdWithoutStore($quote->getId());


           //TODO : New URL
//          $unsubscribeUrl = $this->_storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK) . 'mandrill/autoresponder/unsubscribe?list=abandonedcart&email=' . $email . '&store=' . $storeId;
            $couponcode = '';

            $unsubscribeUrl = false;


                //if hour is set for first run calculates hours since cart was created else calculates days
            $today = idate('U', strtotime(date('Y-m-d H:i:s')));
            $updatedAt = idate('U', strtotime($quote->getUpdatedAt()));
            $updatedAtDiff = ($today - $updatedAt) / 60 / 60 / 24;
            if ($this->unit == \Ebizmarts\AbandonedCart\Model\Config::IN_HOURS && $run == 0) {
                $updatedAtDiff = ($today - $updatedAt) / 60 / 60;
            }


            $mailSend = false;
            try {

                // if days have passed proceed to send mail
                if ($updatedAtDiff >= $diff) {
                    $mailSubject = $this->_getMailSubject($run, $storeId);
                    $templateId = $this->_getTemplateId($run, $storeId);
                    if ($this->sendcoupon && $run + 1 == $this->sendcoupondays) {
                        // create a new coupon
                        if ($this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::COUPON_AUTOMATIC) == 2) {
                            list($couponcode, $discount, $toDate) = $this->_createNewCoupon($storeId, $email );
                            $url .= '&coupon=' . $couponcode;
                            $vars = array('quote' => $quote, 'url' => $url, 'couponcode' => $couponcode, 'discount' => $discount,
                                'todate' => $toDate, 'name' => $name, 'unsubscribeurl' => $unsubscribeUrl);
                        } else {
                            $couponcode = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::COUPON_CODE);
                            $url .= '&coupon=' . $couponcode;
                            $vars = array('quote' => $quote, 'url' => $url, 'couponcode' => $couponcode, 'name' => $name, 'unsubscribeurl' => $unsubscribeUrl);
                        }
                    } else {
                        $vars = array('quote' => $quote, 'url' => $url, 'unsubscribeurl' => $unsubscribeUrl, 'name' => $name, 'subject'=>$mailSubject);
                    }
                    $transport = $this->_transportBuilder->setTemplateIdentifier($templateId)
                        ->setTemplateOptions(['area' => \Magento\Backend\App\Area\FrontNameResolver::AREA_CODE, 'store' => $storeId])
                        ->setTemplateVars($vars)
                        ->setFrom($sender)
                        ->addTo($email, $name)
                        ->getTransport();

                    $transport->sendMessage();
//                $quote2->setEbizmartsAbandonedcartCounter($quote2->getEbizmartsAbandonedcartCounter() + 1);
//                $quote2->setEbizmartsAbandonedcartToken($token);
//                $quote2->save();

                    $mailSend = true;


                    //$this->_objectManager->create('\Ebizmarts\Mandrill\Helper\Data')->saveMail('abandoned cart', $email, $name, $couponcode, $storeId);


                }

            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }

            $quote->setEbizmartsAbandonedcartCounter($quote->getEbizmartsAbandonedcartCounter() + 1);
            $quote->setEbizmartsAbandonedcartToken($token);
            $quote->save();

            break;
        }

    }
    protected function _proccessCollection($quote, $storeId)
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            $removeFromQuote = false;
            $product = $this->_objectManager->create('Magento\Catalog\Model\Product')->load($item->getProductId());
            if (!$product || $product->getStatus() == \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED) {
                //Mage::log('AbandonedCart; ' . $product->getSku() . ' is no longer present or enabled; remove from quote ' . $quote->getId() . ' for email', null, 'Ebizmarts_AbandonedCart.log');
                $removeFromQuote = true;
            }
            $stock = null;
            if ($product->getTypeId() == 'configurable') {
                $simpleProductId = $this->_objectManager->create('Magento\Catalog\Model\Product')->getIdBySku($item->getSku());
                //$simpleProduct = $this->_objectManager->create('Magento\Catalog\Model\Product')->load($simpleProductId);

                $stock = $this->_stockRegistry->getStockItem($simpleProductId);


                // $stock = $simpleProduct->getStockItem();
                $stockQty = $stock->getQty();
            } elseif ($product->getTypeId() == 'bundle') {
                $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
                $bundled_product = $this->_objectManager->create('\Magento\Catalog\Model\Product')->load($product->getId());
                $selectionCollection = $bundled_product->getTypeInstance(true)->getSelectionsCollection(
                    $bundled_product->getTypeInstance(true)->getOptionsIds($bundled_product), $bundled_product
                );
                $stockQty = -1;
                foreach ($selectionCollection as $option) {
                    foreach ($options['bundle_options'] as $bundle) {
                        if ($bundle['value'][0]['title'] == $option->getName()) {
                            $label = $bundle['label'];
                            $qty = $bundle['value'][0]['qty'];
                            if ($stockQty == -1 || $stockQty > $qty) {
                                $stockQty = $qty;
                            }
                        }
                    }
                }

            } else {
                $stock =  $this->_stockRegistry->getStockItem($product->getGetId(),$storeId);//$product->getStockItem();
                $stockQty = $stock->getQty();
            }

            if (
                (
                    is_object($stock) && ($stock->getManageStock() ||
                        ($stock->getUseConfigManageStock() && $this->_helper->getConfig('cataloginventory/item_options/manage_stock', $quote->getStoreId())))
                )
                && $stockQty < $item->getQty()
            ) {
//                Mage::log('AbandonedCart; ' . $product->getSku() . ' is no longer in stock; remove from quote ' . $quote->getId() . ' for email', null, 'Ebizmarts_AbandonedCart.log');
                $removeFromQuote = true;
            }
            if ($removeFromQuote) {
                $quote->removeItem($item->getId());
            }
        }
    }



    /**
     * @param $store
     * @param $email
     * @return array
     */
    protected function _createNewCoupon($store, $email)
    {
        $collection = $this->_objectManager->create('Magento\SalesRule\Model\Rule')->getCollection()
            ->addFieldToFilter('name', ['like' => 'Abandoned coupon ' . $email]);
        if (!count($collection)) {
            $websiteid = $this->_storeManager->getStore()->getWebsiteId();

            $fromDate = date("Y-m-d");
            $toDate = date('Y-m-d', strtotime($fromDate . " + $this->couponexpiredays day"));
            if ($this->coupontype == 1) {
                $action = 'cart_fixed';
                $discount = $this->_storeManager->getStore()->getCurrentCurrencyCode() . "$this->couponamount";
            } elseif ($this->coupontype == 2) {
                $action = 'by_percent';
                $discount = "$this->couponamount%";
            }
            // $customer_group = new Mage_Customer_Model_Group();
            $customer_group = $this->_objectManager->create('Magento\Customer\Model\Group');
            $allGroups = $customer_group->getCollection()->toOptionHash();
            $groups = array();
            foreach ($allGroups as $groupid => $name) {
                $groups[] = $groupid;
            }
            // $coupon_rule = Mage::getModel('salesrule/rule');
            $coupon_rule = $this->_objectManager->create('Magento\SalesRule\Model\Rule');
            $coupon_rule->setName("Abandoned coupon $email")
                ->setDescription("Abandoned coupon $email")
                ->setStopRulesProcessing(0)
                ->setFromDate($fromDate)
                ->setToDate($toDate)
                ->setIsActive(1)
                ->setCouponType(2)
                ->setUsesPerCoupon(1)
                ->setUsesPerCustomer(1)
                ->setCustomerGroupIds($groups)
                ->setProductIds('')
                ->setLengthMin($this->couponlength)
                ->setLengthMax($this->couponlength)
                ->setSortOrder(0)
                ->setStoreLabels(array($this->couponlabel))
                ->setSimpleAction($action)
                ->setDiscountAmount($this->couponamount)
                ->setDiscountQty(0)
                ->setDiscountStep('0')
                ->setSimpleFreeShipping('0')
                ->setApplyToShipping('0')
                ->setIsRss(0)
                ->setWebsiteIds($websiteid);
            // $uniqueId = Mage::getSingleton('salesrule/coupon_codegenerator', array('length' => $this->couponlength))->generateCode();
            $uniqueId = $this->_objectManager->create('Magento\SalesRule\Model\Coupon\Codegenerator')->setLengthMin($this->couponlength)->setLengthMax($this->couponlength)->generateCode();
            $coupon_rule->setCouponCode($uniqueId);
            $coupon_rule->save();
            return array($uniqueId, $discount, $toDate);
        } else {
            $coupon = $collection->getFirstItem();
            if ($coupon->getSimpleAction() == 'cart_fixed') {
                $discount = $this->_storeManager->getStore()->getCurrentCurrencyCode() . ($coupon->getDiscountAmount() + 0);
            } else {
                $discount = $coupon->getDiscountAmount() + 0;
            }
            return array($coupon->getCode(), $discount, $coupon->getToDate());
        }
    }

    /**
     * @param $interval
     * @param $this->unit
     * @return string
     */
    function _getIntervalUnitSql($interval, $unit)
    {
        return sprintf('INTERVAL %d %s', $interval, $unit);
    }

    /**
     * @return string
     */
    function _getSuggestedZeroDate()
    {
        return '0000-00-00 00:00:00';
    }
    /**
     * @param $currentCount
     * @param $store
     * @return mixed|null
     */
    protected function _getMailSubject($currentCount, $store)
    {

        $ret = NULL;
        switch ($currentCount) {
            case 0:
                $ret = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::FIRST_SUBJECT, $store);
                break;
            case 1:
                $ret = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::SECOND_SUBJECT, $store);
                break;
            case 2:
                $ret = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::THIRD_SUBJECT, $store);
                break;
            case 3:
                $ret = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::FOURTH_SUBJECT, $store);
                break;
            case 4:
                $ret = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::FIFTH_SUBJECT, $store);
                break;
        }
        return $ret;

    }

    /**
     * @param $currentCount
     * @return mixed
     */
    protected function _getTemplateId($currentCount, $store)
    {

        $ret = NULL;
        switch ($currentCount) {
            case 0:
                $ret = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::FIRST_EMAIL_TEMPLATE_XML_PATH, $store);
                break;
            case 1:
                $ret = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::SECOND_EMAIL_TEMPLATE_XML_PATH, $store);
                break;
            case 2:
                $ret = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::THIRD_EMAIL_TEMPLATE_XML_PATH, $store);
                break;
            case 3:
                $ret = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::FOURTH_EMAIL_TEMPLATE_XML_PATH, $store);
                break;
            case 4:
                $ret = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::FIFTH_EMAIL_TEMPLATE_XML_PATH, $store);
                break;
        }
        return $ret;

    }

    protected function _cleanCoupons($store)
    {
        $today = date('Y-m-d');
//        $collection = Mage::getModel('salesrule/rule')->getCollection()
        $collection = $this->_objectManager->create('Magento\SalesRule\Model\Rule')
            ->getCollection()
            ->addFieldToFilter('name', ['like' => 'Abandoned coupon%'])
            ->addFieldToFilter('to_date', ['lt' => $today]);

        foreach ($collection as $toDelete) {
            $toDelete->delete();
        }

    }
}
