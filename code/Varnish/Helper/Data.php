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
     * Return varnish servers from configuration
     * 
     * @return array 
     */
    public function getVarnishServers()
    {
        $serverConfig = Mage::getStoreConfig('varnish/options/servers');
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

	# TODO - FIXME - We need to uniq the resulting URLs. We've seen applications that have the same store URL multiple times. This is inefficient.
	foreach ($collection as $store) {
		$urls[] = $store->getBaseUrl() . "/.*";
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
