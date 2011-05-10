<?php

class Magneto_Varnish_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function useVarnishCache(){
        Mage::app()->useCache('varnish');
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
        foreach ($serverConfig as $value) {
            $varnishServers[] = $value;
        }

        return $varnishServers;
    }

    public function purgeAll()
    {
        return $this->purge(array('/.*'));
    }

    /**
     * Purge an array of urls on all varnish servers.
     * 
     * @param array $urls 
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
                $varnishUrl = "http://" . $varnishServer . $url;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $varnishUrl);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

                curl_multi_add_handle($mh, $ch);
                $curlHandlers[] = $ch;
            }
        }

        do {
            $n = curl_multi_exec($mh, $active);
        } while ($active);
        
        // FIXME: Add error handling 

        // Clean up
        foreach ($curlHandlers as $ch) {
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        /*
                    // curl error handling
                    if (curl_errno($ch)){
                        $errors[] = "Cannot purge url {$varnishUrl} due to error {curl_error($ch)}";
                    } else {
                        $info = curl_getinfo($ch);
                        if ($info['http_code']!=200 && $info['http_code']!=404) {
                            $errors[] = "Cannot purge url {$varnishUrl}, http code: {$info['http_code']}";
                            Mage::log("Varnish: cannot purge url {$varnishUrl}, http code: {$info['http_code']}");
                        }
                    }
                }
                catch(Exception $e) {
                    Mage::log('Curl exception ' . $e->getFile(). ' ' . $e->getLine() . ' ' . $e->getMessage());
                }
            }
        } */
    }
}
