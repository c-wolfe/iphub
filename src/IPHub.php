<?php
  
  namespace CWolfe\IPHub;
  
  use Exception;
  use Predis\Client;
  
  /**
   * Class IPHub
   * @package CWolfe\IPHub
   */
  class IPHub {
    
    public const RESIDENTIAL = 0;
    public const NON_RESIDENTIAL = 1;
    public const NON_RESIDENTIAL_AND_RESIDENTIAL = 2;
    
    /**
     * @var string
     */
    private static $key;
    
    /**
     * @var Client
     */
    private $predis;
    
    /**
     * @var string
     */
    private $prefix;
    
    /**
     * IPHub constructor.
     * @param string $apiKey
     * @param string $connectionUrl
     * @param string $prefix
     */
    public function __construct($apiKey, $connectionUrl, $prefix = "iphub") {
      IPHub::$key = $apiKey;
      $this->predis = new Client($connectionUrl);
      $this->prefix = $prefix;
    }
    
    /**
     * @param string $ip
     * @param int $blevel
     * @param bool $strict
     * @return bool Whether the IP should be allowed or not
     */
    public function isAllowed($ip, $blevel = IPHub::RESIDENTIAL, $strict = false) {
      try {
        $level = $this->getIpInformation($ip)['block'];
      } catch (Exception $exception) {
        return !$strict;
      }
      
      if ($level == IPHub::NON_RESIDENTIAL) {
        return $level == IPHub::NON_RESIDENTIAL || $blevel == IPHub::RESIDENTIAL;
      }
      return $blevel == $level;
    }
    
    /**
     * @param string $ip
     * @return array
     * @throws RatelimitException
     * @throws Exception
     */
    public function getIpInformation($ip) {
      
      if (!$this->predis->isConnected()) {
        $this->predis->connect();
      }
      
      if ($this->existsInCache($ip)) {
        return json_decode($this->predis->get("$this->prefix:$ip"));
      } else {
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => "http://v2.api.iphub.info/ip/$ip",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["X-Key: " . IPHub::$key]
        ]);
        
        $response = curl_exec($ch);
        
        if (!curl_error($ch)) {
          $responseHttpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
          if ($responseHttpCode == 200) {
            $responseObject = json_decode($response);
            
            if (isset($responseObject->ip)) {
              $this->pushToCache($ip, $responseObject);
              return $responseObject;
            }
            
          } else if ($responseHttpCode == 429) {
            throw new RatelimitException();
          }
        }
        
      }
      
      return [];
    }
    
    /**
     * @param string $ip
     * @return bool
     */
    public function existsInCache($ip) {
      return $this->predis->exists("$this->prefix:$ip") === 1;
    }
    
    /**
     * @param string $ip
     * @param array $information
     * @param int $ttl
     */
    public function pushToCache($ip, $information, $ttl = 3600) {
      $this->predis->set("$this->prefix:$ip", json_encode($information), 'EX', $ttl);
    }
    
    /**
     * @param string $ip
     * @return int
     * @throws RatelimitException
     * @deprecated
     */
    public function getIpLevel($ip) {
      return $this->getLevel($ip);
    }
    
    
    /**
     * @param string $ip
     * @return int
     * @throws RatelimitException
     */
    public function getLevel($ip) {
      $information = $this->getIpInformation($ip);
      if (is_array($information) && isset($information['block'])) {
        return $information['block'];
      }
      return 0;
    }
    
    /**
     * @param string $ip
     * @return string
     * @throws RatelimitException
     */
    public function getCountryCode($ip) {
      $information = $this->getIpInformation($ip);
      if (is_array($information) && isset($information['countryCode'])) {
        return $information['countryCode'];
      }
      return 0;
    }
    
    /**
     * @param string $ip
     * @return string
     * @throws RatelimitException
     */
    public function getCountryName($ip) {
      $information = $this->getIpInformation($ip);
      if (is_array($information) && isset($information['countryName'])) {
        return $information['countryName'];
      }
      return 0;
    }
    
    /**
     * @param string $ip
     * @return string
     * @throws RatelimitException
     */
    public function getASN($ip) {
      $information = $this->getIpInformation($ip);
      if (is_array($information) && isset($information['asn'])) {
        return $information['asn'];
      }
      return 0;
    }
    
    /**
     * @param string $ip
     * @return string
     * @throws RatelimitException
     */
    public function getISP($ip) {
      $information = $this->getIpInformation($ip);
      if (is_array($information) && isset($information['isp'])) {
        return $information['isp'];
      }
      return 0;
    }
    
    /**
     * @param string $ip
     * @return string
     * @throws RatelimitException
     */
    public function getHostname($ip) {
      $information = $this->getIpInformation($ip);
      if (is_array($information) && isset($information['hostname'])) {
        return $information['hostname'];
      }
      return 0;
    }
    
    /**
     * @param string $ip
     */
    public function removeFromCache($ip) {
      $this->predis->del(["$this->prefix:$ip"]);
    }
    
  }