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

namespace Ebizmarts\AbandonedCart\Controller\Abandoned;


class Loadquote extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    /**
     * @var \Ebizmarts\AbandonedCart\Helper\Data
     */
    protected $_helper;
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var Quote
     */
    protected $_newQuote;


    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Ebizmarts\AbandonedCart\Helper\Data $helper
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Ebizmarts\AbandonedCart\Helper\Data $helper,
        \Ebizmarts\AbandonedCart\Model\Sales\Quote $quote,
        \Psr\Log\LoggerInterface $logger
    )
    {
        parent::__construct($context);
        $this->_objectManager = $context->getObjectManager();
        $this->_helper = $helper;
        $this->_resultPageFactory = $resultPageFactory;

        $this->_newQuote   = $quote;

        $this->_logger = $logger;
    }
    public function execute()
    {
        $this->_logger->info(__METHOD__);
        $quoteId = (int) $this->getRequest()->getParam('id', false);
        $this->_logger->info("quoteid $quoteId");
        if($quoteId) {
            //$quote = $this->_objectManager->create('\Magento\Quote\Model\Quote')->load($quoteId);
            $this->_newQuote->load($quoteId);


            $storeId = $this->_newQuote->getStoreId();
            $url = $this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::PAGE,$storeId);
            $this->_logger->info("url $url");
            $token = (int) $this->getRequest()->getParam('token', false);
            if(!$token || $token != $this->_newQuote->getEbizmartsAbandonedcartToken())
            {
                $this->messageManager->addNotice("Invalid token");
                $this->_redirect($url);
            }
            else {
                $coupon = $this->getRequest()->getParam('coupon', false);
                if($coupon)
                {
                    $this->_newQuote->setCouponCode($coupon);
                }
                $this->_newQuote->setEbizmartsAbandonedcartFlag(1);
                $this->_newQuote->save();
                if(!$this->_newQuote->getCustomerId())
                {
                    $this->_getCheckoutSession()->setQuoteId($this->_newQuote->getId());
                }
                if($this->_helper->getConfig(\Ebizmarts\AbandonedCart\Model\Config::AUTOLOGIN,$storeId))
                {
                    if($this->_newQuote->getCustomerId())
                    {
                        $customerSession = $this->_getCustomerSession();
                        if(!$customerSession->isLoggedIn())
                        {
                            $customerSession->loginById($this->_newQuote->getCustomerId());
                        }
                        $this->_redirect('customer/account');
                    }
                }
                $this->_redirect($url);
            }
        }
    }

    protected function _getCustomerSession()
    {
        return $this->_objectManager->get('Magento\Customer\Model\Session');
    }

    protected function _getCheckoutSession()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }
}