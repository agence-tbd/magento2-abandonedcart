<?php

namespace Ebizmarts\AbandonedCart\Model\Plugin;

class Quote
{

    protected $popupFactory;

    public function __construct(\Ebizmarts\AbandonedCart\Model\PopupFactory $popup)
    {
        $this->popupFactory = $popup;
    }


    /**
     * @param $result
     *
     * @return  \Magento\Quote\Model\Quote
     */
    public function afterLoadByIdWithoutStore($result)
    {
        $this->loadEbizmartData($result);

        return $result;
    }

    /**
     * @param $result
     *
     * @return  \Magento\Quote\Model\Quote
     */
    public function afterLoad($result) {


        $this->loadEbizmartData($result);

        return $result;
    }


    /**
     * @param $result
     *
     * @return  \Magento\Quote\Model\Quote
     */
    public function afterSave($result) {

        $this->saveEbizmartData($result);
        return $result;
    }


    protected function loadEbizmartData($quote, $popupId = 0)
    {
        if($quote->getId()) {
            $popup = $this->popupFactory->create();

            $popup->unsetData();
            $popup->load($quote->getId(), 'quote_id');

            //&& !$quote->hasEbizmartData()


            $quote->setEbizmartsAbandonedcartToken($popup->getToken());
            $quote->setEbizmartsAbandonedcartFlag($popup->getFlag());
            $quote->setEbizmartsAbandonedcartCounter($popup->getCounter());
//            $quote->setEbizmartsAbandonedcartOrderId();

            //$quote->setEbizmartData(true);
        }


    }


    protected function saveEbizmartData($quote)
    {
        if($quote->getId()) {

            $popup = $this->popupFactory->create();
            $popup->load($quote->getId(), 'quote_id');

            if(!$popup->getId()) {
                $popup->setQuoteId($quote->getId());
                $popup->setEmail($quote->getCustomerEmail());
            }

            $popup->setToken($quote->getEbizmartsAbandonedcartToken());
            $popup->setFlag($quote->getEbizmartsAbandonedcartFlag());
            $popup->setCounter($quote->getEbizmartsAbandonedcartCounter());
            $popup->setUpdatedAt((new \DateTime())->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT));

            $popup->save();
        }


    }



}