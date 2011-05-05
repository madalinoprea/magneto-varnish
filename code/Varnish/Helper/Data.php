<?php

class Magneto_Varnish_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function purgeAll()
    {
        
    }

    public function purge(array $urls)
    {
        try {
            $varnishUrl = Mage::getBaseUrl();
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $varnishUrl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $response = curl_exec($ch);
            curl_close($ch);
        }
        catch(Exception  e) {
            Mage::log('Curl exception ' . $e->getFile(). ' ' . $e->getLine() . ' ' . $e->getMessage());
        }
    }
}
