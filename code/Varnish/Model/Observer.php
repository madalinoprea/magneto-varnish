<?php 

class Magneto_Varnish_Model_Observer {

    public function getCookie()
    {
        return Mage::app()->getCookie();
    }

    /**
     * @param $observer Varien_Event_Observer
     */
    public function varnish(Varien_Event_Observer $observer)
    {
        $event = $observer->getEvent();
        $response = $observer->getResponse();
    }

    public function purgeCache($observer)
    {
        $tags = $observer->getTags();
        $urls = array();
        // Mage::log("Tags: " . get_class($tags) . ' = ' . var_export($tags, true));
        
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
                }
            }
        }
        Mage::log("purge all urls: " . var_export($urls, true));
        // FIXME: Perform the actual purge
    }

    /**
     * Returns all the urls related to product
     * @param Mage_Catalog_Model_Product $product
     */
    private function _getUrlsForProduct($product){
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
    private function _getUrlsForCategory($category) {
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
    

    public function turnOnCache()
    {
        $this->getCookie()->delete('nocache');
    }

    public function turnOffCache()
    {
        $this->getCookie()->set('nocache', 1);
    }

    public function canCache()
    {
        return true;
    }

    protected function quoteHasItems()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if($quote instanceof Mage_Sales_Model_Quote && $quote->hasItems()) {
            return true;
        } else {
            return false;
        }
    }

    public function customerIsLogged()
    {
        $customerSession = Mage::getSingleton('customer/session');
        if ($customerSession instanceof Mage_Customer_Model_Session &&
            $customerSession->isLoggedIn() ){
                return true;
        }
        else {
            return false;
        }
    }
}

