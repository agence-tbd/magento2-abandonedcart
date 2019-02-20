<?php
/**
 * Created by PhpStorm.
 * User: vpietri
 * Date: 18/02/19
 * Time: 15:52
 */
namespace Ebizmarts\AbandonedCart\Model\Sales;


class Quote extends \Magento\Quote\Model\Quote
{

    /**
     * Trigger collect totals after loading, if required
     *
     * @return $this
     */
    protected function _afterLoad()
    {


        return parent::_afterLoad();
    }


    /**
     * Processing object after save data
     *
     * @return $this
     */
    public function afterSave()
    {
        return parent::afterSave();
    }
}