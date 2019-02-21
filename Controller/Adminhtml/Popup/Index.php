<?php
/**
 *  Copyright (c) 2018 Agence TBD. All rights reserved.
 *  Developer: Willow DUBUC <willow.dubuc@agence-tbd.com>
 */

namespace Ebizmarts\AbandonedCart\Controller\Adminhtml\Popup;

class Index extends \Magento\Backend\App\Action
{
    protected $resultPageFactory = false;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    )
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((__('Abandoned Cart')));

        return $resultPage;
    }
}
