<?php 

class Magneto_Varnish_Model_Observer {

    /**
     * @param $observer Varien_Event_Observer
     */
    public function varnish(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        $response = $observer->getResponse();
        $helper = Mage::helper('varnish/cacheable'); /* @var $helper Magneto_Varnish_Model_Cacheable */

        if( $helper->isNoCacheStable() ){
            return false;
        }

        if ($helper->pollVerification()) {
            $helper->setNoCacheStable();
            return false;
        }

        if ($helper->quoteHasItems()) {
            $helper->turnOffVarnishCache();
            return false;
        } else {
            $helper->turnOnVarnishCache();
        }

        if ($helper->isCustomerLoggedIn()) {
            $helper->turnOffVarnishCache();
            return false;
        } 

        $helper->turnOnVarnishCache();
    }

    /**
     * Listens to application_clean_cache event and gets notified when a product/category/cms 
     * model is saved.
     *
     * @param $observer Mage_Core_Model_Observer
     */
    public function purgeCache($observer)
    {
        $tags = $observer->getTags();
        $urls = array();
        Mage::log("Tags: " . get_class($tags) . ' = ' . var_export($tags, true));
				
		if($tags == array())
		{
            $errors = Mage::helper('varnish')->purgeAll();
            if (!empty($errors)) {
                Mage::getSingleton('adminhtml/session')->addError(
                    "Varnish Purge failed");
            } else {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    "The Varnish cache storage has been flushed.");
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
                    "Some Varnish purges failed: <br/>" . implode("<br/>", $relativeUrls));
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

        $routePath = 'catalog/product/view';
        $routeParams['id']  = $product->getId();
        $routeParams['s']   = $product->getUrlKey();
        $routeParams['_store'] = (!$store_id ? 1: $store_id);
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
        $routeParams['id']  = $category->getId();
        $routeParams['s']   = $category->getUrlKey();
        $routeParams['_store'] = (!$store_id ? 1 : $store_id); # Default store id is 1
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

