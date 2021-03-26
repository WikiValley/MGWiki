<?php
namespace MediaWiki\Extension\MGWiki\Utilities;

/**
 * Version simplifiée de Status.php
 * Permet de passer des variables en retour via Status->extra()
 */
class MGWStatus {

  /**
   * @param bool
   */
  private $done;

  /**
   * @param string
   */
  private $message;

  /**
   * @param mixed
   * to store any else element
   */
  public $extra;

  public function __construct() {
  }

  public static function new( $done, $message, $extra = null ) {
    $status = new MGWStatus;
    $status->set_done( $done );
    $status->set_message( $message );
    if ( !is_null( $extra ) ) $status->set_extra( $extra );
    return $status;
  }

  public static function newDone( $message = '', $extra = null ) {
    return MGWStatus::new( true, $message, $extra );
  }

  public static function newFailed( $message = '', $extra = null ) {
    return MGWStatus::new( false, $message, $extra );
  }

  public function set_done( $bool ) {
    $this->done = $bool;
  }

  public function set_message( $string ) {
    $this->message = $string;
  }

  public function set_extra( $extra ) {
    $this->extra = $extra;
  }

  public function done() {
    return $this->done;
  }

  public function mess() {
    return $this->message;
  }

  public function extra() {
    return $this->extra;
  }
}
