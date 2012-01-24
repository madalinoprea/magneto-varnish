<?php

class Magneto_Varnish_Helper_Cacheable extends Mage_Core_Helper_Abstract
{

    /**
     * Retrieves current cookie.
     * 
     * @return Mage_Core_Model_Cookie
     */
    public function getCookie()
    {
        return Mage::app()->getCookie();
    }

    public function isNoCacheStable()
    {
        $this->getCookie()->get('nocache_stable') === 1;
    }

    public function setNoCacheStable($value=1){
        $this->getCookie()->set('nocache_stable', $value);
    }

    public function turnOffVarnishCache()
    {   
        $this->getCookie()->set('nocache', 1);
    }

    public function turnOnVarnishCache()
    {
        if ($this->getCookie()->get('nocache'))
        {
            $this->getCookie()->delete('nocache');
        }
    }

    public function isAdminArea()
    {
	// http://freegento.com/doc/dd/dc2/class_mage___core___model___design___package.html
	// http://freegento.com/doc/dc/d33/class_mage___core___model___app___area.html
	//
	// If we are in the admin area of Magento (irrespective of URL), return false
	//

	$design = Mage::getSingleton('core/design_package');

	return $design instanceof Mage_Core_Model_Design_Package && $design->getArea() == "adminhtml";
    }

    public function quoteHasItems()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();

        return $quote instanceof Mage_Sales_Model_Quote && $quote->hasItems();
    }

    public function hasCompareItems()
    {
        // see Mage_Catalog_Helper_Product_Compare
        return Mage::helper('catalog/product_compare')->getItemCount() > 0;
    }

    public function isCustomerLoggedIn()
    {
        $customerSession = Mage::getSingleton('customer/session');

        return $customerSession instanceof Mage_Customer_Model_Session && $customerSession->isLoggedIn();
    }

    public function pollVerification()
    {
        $justVotedPollId = (int) Mage::getSingleton('core/session')->getJustVotedPoll();
        if ($justVotedPollId) {
            return true;
        }
    }
}

