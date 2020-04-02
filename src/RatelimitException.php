<?php
  
  
  namespace CWolfe\IPHub;
  
  use Exception;
  
  class RatelimitException extends Exception {
    
    public function __construct() {
      parent::__construct("Rate limit hit");
    }
    
  }