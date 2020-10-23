<?php
/**
 * Page spéciale demande de création de compte
 *
 */

class SpecialAccountRequest extends \SpecialPage {

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		parent::__construct( 'SpecialAccountRequest' );
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub The subpage string argument (if any).
	 *  [[Special:HelloWorld/subpage]].
	 */
	public function execute( $sub ) {
    global $_SERVER;
    global $_POST;
		global $wgEmergencyContact;

		$out = $this->getOutput();
		$out->addModules('ext.mgwiki-dev-specialaccountrequest');
		$out->setPageTitle( $this->msg( 'specialaccountrequest-title' ) );

    $form1 = '
      <form name="form1" action="' . $_SERVER['PHP_SELF'] . '" method="post" id="mgw-accountrequest-form" class="mgw-accountrequest">
				<table>
					<tr><td id="institution-label">Institution de rattachement</td>
						<td onclick="mw.accountRequest()">
							<input type="radio" id="lyon1"  name="institution" value="lyon1"  ><label for="lyon1" >Université Lyon 1</label><br>
							<input type="radio" id="adepul" name="institution" value="adepul" ><label for="adepul">Adepul</label><br>
							<input type="radio" id="autre"  name="institution" value="autre"  ><label for="adepul">Autre</label>
						</td>
					</tr>
				</table>';
		$form2 = '
				<table class="mgw-hidden">
	        <tr><td><label for="nom"	 >Nom    </label></td><td><input type="text"  name="nom"    required></td></tr>
	        <tr><td><label for="prenom">Prénom </label></td><td><input type="text"  name="prenom" required></td></tr>
	        <tr><td><label for="email" >Email  </label></td><td><input type="email" name="email"  required></td></tr>

	        <tr><td class="mgw-tr-formateur"></td><td><input type="text" name="formateur" class="mgw-tr-formateur"                    ></tr></td>
	        <tr><td class="mgw-tr-year"     ></td><td><input type="text" name="year"      class="mgw-tr-year"  size="4" maxlength="4" ></tr></td>
	      </table>

				<div class="mgw-hidden" id="mgw-accountrequest-comment">
 	       <p id="mgw-p-comment">Commentaires<br>
	       	<textarea id="mgw-textarea-comment" name="comment" rows="5" cols="80"></textarea>
				 </p>
	      </div>

	      <input type="submit" value="Envoyer la demande" name="accountRequest"  class="mgw-hidden"/>
      </form>';


    $mess =	'<p id="mgw-accountrequest-ok" class="mgw-accountrequest">Votre demande a bien été envoyée à l\'administrateur du site.<p>
                <button type="button" onclick="mw.mgwHome()" > retour à la page d\'accueil</button>';

    if (isset($_POST['accountRequest'])){
			$mailer = new \UserMailer();
			$mail_to = new \MailAddress($wgEmergencyContact);
			$mail_from = new \MailAddress($_POST['email']);
			$year = isset($_POST['year']) ? $_POST['year'] : "null";
			$comment = isset($_POST['comment']) ? $_POST['comment'] : "null";
			$body ='
Demande de création de compte MGWiki

Date: ' . date('Y-m-d H:i:s') . '
Institution: ' . $_POST['institution'] . '
Nom: ' . ucfirst(strtolower($_POST['prenom'])) . ' ' . strtoupper($_POST['nom']) . '
Formateur/Formation: ' . $_POST['formateur'] . '
Année: ' . $year . '
Commentaires: ' . $comment . '
Email: ' .  $_POST['email'] ;

			$mailer->send( array($mail_to), $mail_from, 'Demande de création de compte', $body );
      $out->addHTML($mess);
    }
    else {
      $out->addHTML($form1);
			$out->addWikiTextAsContent($this->msg( 'specialaccountrequest-intro-lyon' ));
			$out->addWikiTextAsContent($this->msg( 'specialaccountrequest-intro-adepul' ));
			$out->addWikiTextAsContent($this->msg( 'specialaccountrequest-intro-autre' ));
      $out->addHTML($form2);
    }
  }

	/**
	 * groupe auquel se rapporte cette page spéciale
	 */
	protected function getGroupName() {
		return 'mgwiki';
	}
}
