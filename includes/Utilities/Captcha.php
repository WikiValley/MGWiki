<?php

namespace MediaWiki\Extension\MGWikiDev\Utilities;

class Captcha
{
  private $answers = array();

  function __construct()
  {
    global $IP;
    $this->answers = json_decode(file_get_contents($IP . "/extensions/MGWikiDev/data/Private/Captcha.json"), true);
  }

  public function getRandomKey()
  {
    return array_rand($this->answers);
  }

  public function isValid($key, $response)
  {
    return $this->getAnswer($key) === $this->sanitize($response);
  }

  private function getAnswer($key)
  {
    return isset($this->answers[$key]) ? htmlspecialchars($this->answers[$key]) : false;
  }

  private function sanitize($response)
  {
    return strtolower(str_replace('.',',',$response));
  }
}
