<?php

class Magneto_Varnish_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * Check if varnish is enabled in Cache management.
     * 
     * @return boolean  True if varnish is enable din Cache management. 
     */
    public function useVarnishCache(){
        return Mage::app()->useCache('varnish');
    }

    /**
     * Return excluded URLs from configuration
     *
     * @return array
     */

	public function getExcludedURLs()
	{
		$excludeConfig = Mage::getStoreConfig('varnish/excludes/exclude');
		$excludeURLs   = array();

		foreach (explode("\n", $excludeConfig) as $value) {

			// Ensure that is not an empty line
			if (preg_match('/\S/', $value)) {
				$excludeURLs[] = trim($value);
			}
		}

		return $excludeURLs;
	}

    /**
     * Return varnish servers from configuration
     * 
     * @return array 
     */
    public function getVarnishServers()
    {
        $serverConfig = Mage::getStoreConfig('varnish/server_options/servers');
        $varnishServers = array();
        
        foreach (explode(',', $serverConfig) as $value ) {
            $varnishServers[] = trim($value);
        }

        return $varnishServers;
    }

    /**
     * Purges all cache on all Varnish servers.
     * 
     * @return array errors if any
     */
    public function purgeAll()
    {
	$collection = Mage::getModel("core/store")->getCollection();
	$urls = Array();

	foreach ($collection as $store) {
		# TODO: this needs support in the VCL through ban() in Varnish 3.0 .
		$urls[] = $store->getBaseUrl() . ".*";

		# Sometimes we see multiple storefronts with the same frontend URL. We do not need to flush those URLs twice. So uniq and sort.
		$urls = array_values(array_unique($urls));
	}

	$this->purge($urls);
    }

    /**
     * Purge an array of urls on all varnish servers.
     * 
     * @param array $urls
     * @return array with all errors 
     */
    public function purge(array $urls)
    {
        $varnishServers = $this->getVarnishServers();
        $errors = array();

        // Init curl handler
        $curlHandlers = array(); // keep references for clean up
        $mh = curl_multi_init();

	// Uniq the URL's so we don't flood the console with duplicate URLs
	$urls = array_values(array_unique($urls));
        
        foreach ((array)$varnishServers as $varnishServer) {
	    foreach ($urls as $url) {
		$urlpath = parse_url($url, PHP_URL_PATH);
		$urlhost = parse_url($url, PHP_URL_HOST);

                $varnishUrl = "http://" . $varnishServer . $urlpath;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $varnishUrl);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);

		if ($urlhost) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("Host: $urlhost"));
		}

                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

                curl_multi_add_handle($mh, $ch);
                $curlHandlers[] = $ch;
            }
        }

        do {
            $n = curl_multi_exec($mh, $active);
        } while ($active);
        
        // Error handling and clean up
        foreach ($curlHandlers as $ch) {
            $info = curl_getinfo($ch);
            
            if (curl_errno($ch)) {
                $errors[] = "Cannot purge url {$info['url']} due to error" . curl_error($ch);
            } else if ($info['http_code'] != 200 && $info['http_code'] != 404) {
                $errors[] = "Cannot purge url {$info['url']}, http code: {$info['http_code']}";
            }
            
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        
        return $errors;
    }
}
