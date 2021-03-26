<?php

namespace MediaWiki\Extension\MGWiki\Modules\Json;

use MediaWiki\Extension\MGWiki\Modules\Json\Captcha;
use MediaWiki\Extension\MGWiki\Modules\Json\GetJsonPage;

/**
  * Class to set forms directly from a .json wiki page.
  * Exemple: MediaWiki:Specialaccountrequest.json
  *
  * Modules:
  *   POST data
  *     new JsonToForm( $jsonService, &$postData )
  *   HTML form
  *     JsonToForm->makeForm( $message = '', $displayCaptcha = false )
  *   Captcha
  *     JsonToForm->isCaptchaPosted()
  *     JsonToForm->isCaptchaValid()
  *   Email
  *     JsonToForm->sendEmail()
  *   Onclick
  *     form:*:type:radio
  *     template
  *   Divers
  *     JsonToForm->getHash()
  *
  * TODO: le module onclick n'est implémenté que sur les <input type="radio">
  */
class JsonToForm
{
  /**
   * @var string
   */
  private $formName;

  /**
   * @var string
   */
  private $prefix;

	/**
	 * @var GetJsonPage
	 */
  private $Json;

	/**
	 * @var Captcha
	 */
  private $Captcha;

	/**
	 * @var array issued from WebRequest::getPostValues()
	 */
  private $postData;

	/**
	 * @var array [ ['type' => 'html'/'wiki', 'value' => (string) ] ]
	 */
  private $output;

	/**
   * @param string $jsonService
   * @return array $this->output
	 */
  public function __construct( $jsonService, &$postData )
  {
    $this->formName = $jsonService;
    $this->prefix = 'mgw-' . $this->formName;

		$this->Json = new GetJsonPage( $jsonService );
    if ( is_null( $this->Json->getSubData( 'show', ['structure'] ) ) )
      throw new \Exception('Erreur à la construction de JsonToForm depuis la page ' .
      \MWNamespace::getCanonicalName( constant( wfMgwConfig('json-pages', $jsonService )['namespace'] ) ) . ':' .
      wfMgwConfig('json-pages', $jsonService )['title'] . ' :  { "structure" : "show" } = null.', 1)
    ;

    self::hydratePostData($postData);

    $this->Captcha = new Captcha();
  }

  private function hydratePostData( &$postData )
  {
    if ( isset( $postData[$this->formName] ) ) {
      foreach ( $postData as $itemKey => $itemData ) {
        $this->postData[$itemKey] = htmlspecialchars( $postData[$itemKey] );
      }
    }
  }

	/**
   * @param string $message: message passé au format HTML
   * @param bool $displayCaptcha : si vrai l'élément 'captcha' doit prendre une de ces valeurs : ['beforesubmit', 'aftersubmit']
   * @return array $this->output
	 */
  public function makeForm( $message = '' )
  {
    # variables
    global $_SERVER;

    # vérification des constructeurs
    if ( !is_null( $this->Json->getSubData( 'captcha' ) ) &&
      !in_array( $this->Json->getSubData( 'captcha' ), ['beforesubmit', 'aftersubmit'] ) )
    {
      throw new \Exception('Erreur à la construction de JsonToForm depuis la page ' .
        \MWNamespace::getCanonicalName( constant( wfMgwConfig('json-pages', $jsonService )['namespace'] ) ) . ':' .
        wfMgwConfig('json-pages', $jsonService )['title'] . ' : { [...] "captcha" : "' . $displayCaptcha .
        '" } : ! in_array( "' . $displayCaptcha . '", ["beforesubmit", "aftersubmit"])', 1) ;
    }
    $controllers = $this->Json->getSubData( 'controllers' );
    if ( !is_null( $controllers ) ) {
      if ( !is_array( $controllers ) )
        throw new \Exception('Erreur à la construction de JsonToForm depuis la page ' .
        \MWNamespace::getCanonicalName( constant( wfMgwConfig('json-pages', $jsonService )['namespace'] ) ) . ':' .
        wfMgwConfig('json-pages', $jsonService )['title'] . ' : { "controllers" :  } doit contenir un tableau', 1) ;
      foreach ( $controllers as $controllerKey => $controllerArray ) {
        if ( !is_array( $controllerArray ) )
          throw new \Exception('Erreur à la construction de JsonToForm depuis la page ' .
          \MWNamespace::getCanonicalName( constant( wfMgwConfig('json-pages', $jsonService )['namespace'] ) ) . ':' .
          wfMgwConfig('json-pages', $jsonService )['title'] . ' : { "controllers" : "' . $controllerKey . '" : } doit contenir un tableau', 1);
      }
    }

    # construction
    $this->htmlOut(
      '<form name="' . $this->formName . '" action="' . $_SERVER['PHP_SELF']
      . '" method="post" id="' . $this->prefix .  '" >'
    );
    foreach ( $this->Json->getSubData('structure') as $displayKey => $displayValue ) {
      $hide = ( $displayKey == 'hide' );
      foreach ( $displayValue as $elementKey => $elementValue ) {
        switch ( $elementKey )
        {
          case "form":
            self::makeFormElement( $elementValue, $hide );
            break;
          case "template":
            self::makeTemplateElement( $elementValue, $hide );
            break;
          case "message":
            $this->htmlOut( $message );
            break;
          case "captcha":
            if ( $elementValue == 'beforesubmit' ||
              ( $elementValue == 'aftersubmit' && isset( $this->postData[ $this->formName ] ) )
            ) self::makeCaptchaElement( $hide );
            break;
          case "submit":
            $class = ($hide) ? ' class="mgw-hidden mgw-' . $this->formName . '-field mgw-' . $this->formName . '-field-hide"' : 'class="mgw-specialaccountrequest-field"';
            $style = ($hide) ? ' style="display:none"' : '';
            $this->htmlOut('
              <input type="submit" id="' . $this->prefix . '-submit-field" value="" name="' . $this->formName .
              '" fieldname="submit" fieldtype="submit"' . $class . $style . '/>'
            );
        }
      }
    }
    $this->htmlOut( '</form>' );
    return $this->output;
  }

  private function makeFormElement( $elementValue, $hide )
  {
    $class = ($hide) ? ' class="mgw-hidden"' : '';
    $style = ($hide) ? ' style="display:none"' : '';
    $this->htmlOut( '<table ' . $class . $style . '> ');
    foreach ($elementValue as $fieldKey => $fieldValue) {

      # préparation des attributs
      $label = ( isset( $fieldValue['label'] ) ) ? $fieldValue['label'] : '';
      $prefix_field = $this->prefix . '-' . $fieldKey;
      $attributes = '';
      if ( isset( $fieldValue['attributes'] )
          && is_array( $fieldValue['attributes'] )
          && sizeof( $fieldValue['attributes'] ) > 0
        ) {
        foreach ( $fieldValue['attributes'] as $attributeKey => $attributeValue ) {
          $attributes .= ' ' . $attributeKey . '="' . $attributeValue . '" ';
        }
      }

      # instanciation de la légende
      $subclass = ($hide) ? ' mgw-' . $this->formName . '-field-hide' : '';
      $class = 'class="mgw-' . $this->formName . '-field' . $subclass . '"';
      $row = '<tr id="mgw-' . $this->formName . '-' . $fieldKey . '" ' . $class . 'fieldtype="' . $fieldValue['type'] .
              '" ' . 'fieldname="' . $fieldKey . '" >
              <td id="' . $prefix_field . '-label" >' . $label . '</td>';

      # instantiation du champs
      $row .= '<td >';
      $id = 'id="mgw-' . $this->formName . '-' . $fieldKey . '-field"';

      ## RADIO
      if ( $fieldValue['type'] == 'radio' )
      {
        $hideclass = ($hide) ? ' mgw-radio-hide' : '';
        foreach ($fieldValue['values'] as $radioKey => $radioValue)
        {
          $div = 'id="' . $prefix_field . '-' . $radioKey . '-field" class="' . $this->prefix . '-radiovalue'. $hideclass .'"
            fieldkey="' . $fieldKey . '" radiokey="' . $radioKey . '"';
          $label = ( isset( $radioValue[ 'label' ] ) ) ? $radioValue[ 'label' ] : '';
          if ( !isset($this->postData[$fieldKey]) || $this->postData[$fieldKey] == '' ) {
            $checked = ( $radioValue['checked'] == 'true' ) ? "checked" : "";
          }
          else {
            $checked = ( $this->postData[$fieldKey] == $radioKey ) ? "checked" : "";
          }
          $row .= '<div ' . $div . '>
            <input type="radio" name="' . $fieldKey . '" value="' . $radioKey . '"  ' . $checked . ' ' . self::onclick( $fieldKey ) . '>
            <label for="' . $radioKey . '" >' . $label . '</label></div>';
        }
        $row .='</td></tr>';
      }

      ## TEXT
      elseif ( in_array( $fieldValue['type'], ['text', 'email'] ) )
      {
        $row .= '<input type="' . $fieldValue[ 'type' ] . '" name="' . $fieldKey
          . '" value="' . $this->postData[ $fieldKey ] . '" ' . $id . ' ' . $attributes .'></td></tr>' ;
      }

      ## TEXTAREA
      elseif ( $fieldValue['type'] == 'textarea' ) {
        $row .= '<textarea name="' . $fieldKey . '" ' . $id . ' '. $attributes .'>'
          . $this->postData[ $fieldKey ] . '</textarea></td></tr>' ;
      }
      $this->htmlOut( $row );
    }

    # clôture
    $this->htmlOut( '</table>' );
  }

  private function makeTemplateElement( $elementValue, $hide)
  {
    foreach ( $elementValue as $id => $string ) {

      $hideClass = ($hide) ? " mgw-hidden" : '';
      $hideStyle = ($hide) ? ' style="display:none" ' : '';

      if ( preg_match('/\$/', $string ) != 1 ) {
        $this->htmlOut( '<span id="' . $this->prefix . '-template-' . $id .
                        '" class="' . $this->prefix . '-template ' . $hideClass . '" ' .
                        $hideStyle .' ' . self::onclick( 'template' ) . '>' );
        $this->wikiOut( '{{' . $string . '}}' );
        $this->htmlOut( '</span>' );
      }
      else {
        $string = explode( "$", $string );
        $cases = $this->Json->getSubData( 'values', [ $string[1] ] );
        foreach ( $cases as $caseKey => $caseValue )
        {
          $this->htmlOut( '<span id="' . $this->prefix . '-template-' . $id . '-' . $caseKey .
                          '" class="' . $this->prefix . '-template ' . $hideClass . '" ' .
                          $hideStyle . ' ' . self::onclick( 'template' ) .
                          'showonfield="' . $string[1] . '" showonvalue="' . $caseKey . '">' ) ;
          $this->wikiOut( '{{' . $string[0] . $caseKey . '}}' );
          $this->htmlOut( '</span>' );
        }
      }
    }
  }

  private function makeCaptchaElement( $hide )
  {
    $class = ($hide) ? ' mgw-hidden' : '';
    $style = ($hide) ? ' style="display:none"' : '';
    $captchaKey = $this->Captcha->getRandomKey();
    $this->htmlOut( '
       <fieldset id="' . $this->prefix . '-captcha" class="mgw-captcha' . $class . '"' . $style . ' ><i>' . $captchaKey . '</i><br>
          <legend>' . wfMessage( $this->formName . '-label-captcha' )->text() . '</legend>
         <input type="text" name="captchaResponse" id="captchaResponse" type="text" />
         <input type="text" name="captchaKey" value="' . $captchaKey . '" id="captchaKey" type="text" hidden/>
       </fieldset>' );
  }

  private function htmlOut( $content )
  {
    $this->output[] = [ 'type' => 'html', 'content' => $content ];
  }

  private function wikiOut( $content )
  {
    $this->output[] = [ 'type' => 'wiki', 'content' => $content ];
  }

  private function onclick( $element )
  {
    if ( !is_null( $this->Json->getSubData( $element, ['onclick'] ) ) ) {
      foreach ( $this->Json->getSubData( 'onclick' ) as $key => $value ) {
        if ( array_key_exists( $element, $value ) )
          return 'onclick="mw.' . $this->formName . '()" '; //
      }
    }
    else return '';
  }

  public function sendEmail()
  {
    $mailer = new \UserMailer();
    $mail_to = $this->mailAdress( 'mailto' );
    $mail_from = $this->mailAdress( 'mailfrom' );
    $body = $this->composeEmail();
    $mailer->send(
      array($mail_to, $mail_from), 							//to
      $mail_to,                    							//from
      wfMessage( $this->formName . '-email-subject' )->plain(),	//subject
      $body,																		//body
      array(																		//options
        'replyTo' => $mail_from,
        'contentType' => 'text/html; charset=UTF-8')
    );
  }

  private function mailAdress( $dest )
  {
    $controller = $this->Json->getSubData( $dest, ['sendmail'] );
    if ( isset( $controller['getvalue'] ) ) {
      $email = $this->postData[$controller['getvalue']];
    }
    elseif ( isset( $controller['setonvalue'] ) ) {
      $email = $this->Json->getSubData( 'setmail', [ $this->postData[$controller['setonvalue']] ] );
    }
    switch ( $controller['type'] ) {
      case 'user':
        $user = \User::newFromName( $email );
        return \MailAddress::newFromUser( $user );
        break;
      case 'email':
        return new \MailAddress( $email );
        break;
    }
  }

	private function composeEmail()
	{
		$body = '
			<body>
				<p>' . wfMessage( $this->formName . '-email-intro')->text() . '</p>
				<p>Votre message :</p>
				<table>
					<tr><td><i>Date: </i></td><td>' . date('Y-m-d H:i:s') . '</td></tr>' ;

    $fields = [];
    # tous les champs "show"
    foreach ( $this->Json->getSubData( 'form', ['show'] ) as $fieldKey => $fieldValue ) {
      $fields[$fieldKey] = $fieldValue;
    }
    # les champs "hide" selon les valeurs de "changefields"
    if ( !is_null( $this->Json->getSubData( 'changeform' ) ) ) {
      foreach ( $this->Json->getSubData('changeform' ) as $key => $value ) {
        if ( $value['onfield'] == 'any' || in_array( $this->postData[ $value['onfield'] ], $value['onvalues'] ) ) {
          foreach ( $this->Json->getSubData( $this->postData[$key], ['changefields', '$'.$key ] ) as $fieldKey => $fieldValue ) {
            $fields[$fieldKey] = $fieldValue;
          }
        }
      }
    }

    foreach ( $fields as $fieldKey => $fieldValue ) {
      if ( is_null( $this->Json->getSubData( 'hidden', [ $fieldKey ] ) ) &&  $fieldKey != 'submit' )
        $body .= '<tr><td><i>' . $fieldValue[ 'label' ] . ': </i></td>
                <td>' . $this->postData[ $fieldKey ] . '</td></tr>';
    }

		$body .= '</table>
    				<br>
    				<p>' . wfMessage( $this->formName . '-email-end' )->text() . '</p>
    			</body>';

		return $body;
	}

  public function isCaptchaPosted() {
    return isset( $this->postData['captchaResponse'] );
  }

  public function isCaptchaValid()
  {
    if ( isset($this->postData['captchaKey']) && isset($this->postData['captchaResponse']) ){
      return $this->Captcha->isValid($this->postData['captchaKey'], $this->postData['captchaResponse']);
    }
    else return false;
  }

  /**
   * gives a unique key from the posted data
   * @return string
   */
  public function getHash()
  {
    $string = '';
    $i = 0;
    foreach ($this->postData as $key => $value) {
      $string .= $value ;
      $i++;
      if ($i > 2) { break; }
    }
    return md5( $string );
  }
}
