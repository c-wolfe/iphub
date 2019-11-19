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
     * @return bool Whether the IP should be allowed or not
     * @throws RatelimitException
     */
    public function isAllowed($ip, $blevel = IPHub::RESIDENTIAL) {
      $level = $this->getIpLevel($ip);
      if ($level == IPHub::NON_RESIDENTIAL) {
        return $level == IPHub::NON_RESIDENTIAL || $blevel == IPHub::RESIDENTIAL;
      }
      return $blevel == $level;
    }
    
    /**
     * @param $ip
     * @return int
     * @throws RatelimitException
     * @throws Exception
     */
    public function getIpLevel($ip) {
      
      if (!$this->predis->isConnected()) {
        $this->predis->connect();
      }
      
      if ($this->existsInCache($ip)) {
        return $this->predis->get($ip);
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
            
            if (isset($responseObject->block)) {
              $this->pushToCache($ip, $responseObject->block);
              return $responseObject->block;
            }
            
          } else if ($responseHttpCode == 429) {
            throw new RatelimitException();
          }
        }
        
      }
      
      return 0;
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
     * @param int $level
     * @param int $ttl
     */
    public function pushToCache($ip, $level, $ttl = 3600) {
      $this->predis->set("$this->prefix:$ip", $level, 'EX', $ttl);
    }
    
    /**
     * @param $ip
     */
    public function removeFromCache($ip) {
      $this->predis->del(["$this->prefix:$ip"]);
    }
    
  }