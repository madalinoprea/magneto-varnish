<?php 

class Magneto_Varnish_Model_Observer {

    /**
     * This method is called when http_response_send_before event is triggered to identify
     * if current page can be cached and set correct cookies for varnish.
     * 
     * @param $observer Varien_Event_Observer
     */
    public function varnish(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        $helper = Mage::helper('varnish/cacheable'); /* @var $helper Magneto_Varnish_Model_Cacheable */

        // Cache disabled in Admin / System / Cache Management
        if( !Mage::app()->useCache('varnish') ){
            $helper->turnOffVarnishCache();
            return false;
        }

        if( $helper->isNoCacheStable() ){
            return false;
        }

        if ($helper->pollVerification()) {
            $helper->setNoCacheStable();
            return false;
        }

        
        if ($helper->quoteHasItems() || $helper->isCustomerLoggedIn() || $helper->hasCompareItems()) {
            $helper->turnOffVarnishCache();

            return false;
        } else {
            $helper->turnOnVarnishCache();
        }

        $helper->turnOnVarnishCache();
    }
    
    /**
     * @see Mage_Core_Model_Cache
     * 
     * @param Mage_Core_Model_Observer $observer 
     */
    public function onCategorySave($observer)
    {
        $category = $observer->getCategory(); /* @var $category Mage_Catalog_Model_Category */
        if ($category->getData('include_in_menu')) {
            // notify user that varnish needs to be refreshed
            Mage::app()->getCacheInstance()->invalidateType(array('varnish'));
        }
        
        return $this;
    }

    /**
     * Listens to application_clean_cache event and gets notified when a product/category/cms 
     * model is saved.
     *
     * @param $observer Mage_Core_Model_Observer
     */
    public function purgeCache($observer)
    {
        // If Varnish is not enabled on admin don't do anything
        if (!Mage::app()->useCache('varnish')) {
            return;
        }
        
        $tags = $observer->getTags();
        $urls = array();

        if ($tags == array()) {
            $errors = Mage::helper('varnish')->purgeAll();
            if (!empty($errors)) {
                Mage::getSingleton('adminhtml/session')->addError("Varnish Purge failed");
            } else {
                Mage::getSingleton('adminhtml/session')->addSuccess("The Varnish cache storage has been flushed.");
            }
            return;
        }

        // compute the urls for affected entities 
        foreach ((array)$tags as $tag) {
            //catalog_product_100 or catalog_category_186
            $tag_fields = explode('_', $tag);
            if (count($tag_fields)==3) {
                if ($tag_fields[1]=='product') {
                    // Mage::log("Purge urls for product " . $tag_fields[2]);

                    // get urls for product
                    $product = Mage::getModel('catalog/product')->load($tag_fields[2]);
                    $urls = array_merge($urls, $this->_getUrlsForProduct($product));
                } elseif ($tag_fields[1]=='category') {
                    // Mage::log('Purge urls for category ' . $tag_fields[2]);

                    $category = Mage::getModel('catalog/category')->load($tag_fields[2]);
                    $category_urls = $this->_getUrlsForCategory($category);
                    $urls = array_merge($urls, $category_urls);
                } elseif ($tag_fields[1]=='page') {
                    $urls = $this->_getUrlsForCmsPage($tag_fields[2]);
                }
            }
        }

        // Transform urls to relative urls
        $relativeUrls = array();
        foreach ($urls as $url) {
            $relativeUrls[] = parse_url($url, PHP_URL_PATH);
        }
        // Mage::log("Relative urls: " . var_export($relativeUrls, True));
        
        if (!empty($relativeUrls)) {
            $errors = Mage::helper('varnish')->purge($relativeUrls);
            if (!empty($errors)) {
                Mage::getSingleton('adminhtml/session')->addError(
                    "Some Varnish purges failed: <br/>" . implode("<br/>", $errors));
            } else {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    "Purges have been submitted successfully: <br/>" . implode("<br/>", $relativeUrls));
            }
        }

        return $this;
    }

    /**
     * Returns all the urls related to product
     * @param Mage_Catalog_Model_Product $product
     */
    protected function _getUrlsForProduct($product){
        $urls = array();

        $store_id = $product->getStoreId();
        $defaultStoreId = Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStore()->getStoreId();

        $routePath = 'catalog/product/view';
        $routeParams['id']  = $product->getId();
        $routeParams['s']   = $product->getUrlKey();
        $routeParams['_store'] = (!$store_id ? $defaultStoreId: $store_id); # Use Default Store ID if Store ID not set.
        $url = Mage::getUrl($routePath, $routeParams);
        $urls[] = $url;

        // Collect all rewrites
        $rewrites = Mage::getModel('core/url_rewrite')->getCollection();
        if (!Mage::getConfig('catalog/seo/product_use_categories')) {
            $rewrites->getSelect()
            ->where("id_path = 'product/{$product->getId()}'");
        } else {
            // Also show full links with categories
            $rewrites->getSelect()
            ->where("id_path = 'product/{$product->getId()}' OR id_path like 'product/{$product->getId()}/%'");
        }
        foreach($rewrites as $r) {
            unset($routeParams);
            $routePath = '';
            $routeParams['_direct'] = $r->getRequestPath();
            $routeParams['_store'] = $r->getStoreId();
            $url = Mage::getUrl($routePath, $routeParams);
            $urls[] = $url;
        }

        return $urls;
    }


    /** 
     * Returns all the urls pointing to the category
     */
    protected function _getUrlsForCategory($category) {
        $urls = array();
        $routePath = 'catalog/category/view';

        $store_id = $category->getStoreId();
        $defaultStoreId = Mage::app()->getWebsite(true)->getDefaultGroup()->getDefaultStore()->getStoreId();
        
        $routeParams['id']  = $category->getId();
        $routeParams['s']   = $category->getUrlKey();
        $routeParams['_store'] = (!$store_id ? $defaultStoreId : $store_id); # Use Default Store ID if Store ID not set.
        $url = Mage::getUrl($routePath, $routeParams);
        $urls[] = $url;

        // Collect all rewrites
        $rewrites = Mage::getModel('core/url_rewrite')->getCollection();
        $rewrites->getSelect()->where("id_path = 'category/{$category->getId()}'");
        foreach($rewrites as $r) {
            unset($routeParams);
            $routePath = '';
            $routeParams['_direct'] = $r->getRequestPath();
            $routeParams['_store'] = $r->getStoreId();
            $routeParams['_nosid'] = True;
            $url = Mage::getUrl($routePath, $routeParams);
            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * Returns all urls related to this cms page
     */
    protected function _getUrlsForCmsPage($cmsPageId)
    {
        $urls = array();
        $page = Mage::getModel('cms/page')->load($cmsPageId);
        if ($page->getId()) {
            $urls[] = '/' . $page->getIdentifier();
        }

        return $urls;
    }
}

