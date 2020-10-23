<?php
/**
 * Page spéciale demande de création de compte
 *
 */

class SpecialAccountRequest extends \SpecialPage {

	private $form;
	private $mess = "";
	private $done = false;

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		parent::__construct( 'SpecialAccountRequest' );
	}

	/**
	 * Form, captcha and $_POST checks.
	 * HTML content definition.
	 */
	protected function beforeExecute( $subPage ) {
    global $_SERVER;
    global $_POST;
		global $_COOKIE;
		global $wgEmergencyContact;
		include ('captcha.php');
		$post1 = (isset($_POST['accountRequest'])) ? true :false;
		$post2 = (isset($_POST['captchaResponse'])) ? true :false;

		//récupération des variables si elles existent
		if ($post1){
			$post_lyon1 = ($_POST['institution'] == 'lyon1')? "checked":"";
			$post_adepul = ($_POST['institution'] == 'adepul')? "checked":"";
			$post_autre = ($_POST['institution'] == 'autre')? "checked":"";
			$post_nom = htmlspecialchars($_POST['nom']);
			$post_prenom = htmlspecialchars($_POST['prenom']);
			$post_email = htmlspecialchars($_POST['email']);
			$post_formateur = htmlspecialchars($_POST['formateur']);
			$post_year = htmlspecialchars($_POST['year']);
			$post_comment = htmlspecialchars($_POST['comment']);
		}
		else {
			$post_lyon1 = "";
			$post_adepul = "";
			$post_autre = "";
			$post_nom = "";
			$post_prenom = "";
			$post_email = "";
			$post_formateur = "";
			$post_year = "";
			$post_comment = "";
		}

		$cookieLabel = str_replace(array('=',',',';','\t','\r','\n','\013','\014'),'','mgw_'.$post_nom.$post_prenom);
		//vérification du captcha, envoi du mail si ok
    if ($post2){
			if (isset($_COOKIE[$cookieLabel])){
				$this->mess =	'
					<p id="mgw-accountrequest-ok" class="mgw-accountrequest">Votre demande déjà envoyée.<p>
					<button type="button" onclick="mw.mgwHome()" > retour à la page d\'accueil</button>';
				$this->done = true;
			}
			elseif (strtolower(str_replace('.',',',$_POST['captchaResponse'])) == $captcha[$_POST['captchaKey']]) {
				$mailer = new \UserMailer();
				$mail_to = new \MailAddress($wgEmergencyContact);
				$mail_from = new \MailAddress($post_email);
				$body ='Merci beaucoup de votre intérêt pour MGWiki.

------ Votre message : -------
Date: ' . date('Y-m-d H:i:s') . '
Institution: ' . $_POST['institution'] . '
Nom: ' . htmlspecialchars_decode(ucfirst(strtolower($post_prenom))) . ' ' . htmlspecialchars_decode(strtoupper($post_nom)) .'
Email: ' .  htmlspecialchars_decode($post_email);
 				if ($_POST['institution']=='lyon1'){$body .= '
Tuteur: ' . htmlspecialchars_decode($post_formateur) . '
Année de promotion: ' . htmlspecialchars_decode($post_year);}
				if ($_POST['institution']=='adepul'){$body .= '
Formation: ' . htmlspecialchars_decode($post_formateur);}
				$body .= '
 Commentaires:
' . htmlspecialchars_decode($post_comment) . '
------------------------------

Nous essayons de donner suite à votre demande dans les meilleurs délais.';

				$mailer->send( array($mail_to,$mail_from), $mail_to, 'MGWiki: demande de création de compte', $body, array('replyTo'=>$mail_from) );
				setcookie($cookieLabel,"sent", time()+3600);
				$this->mess =	'
					<p id="mgw-accountrequest-ok" class="mgw-accountrequest">Votre demande a bien été envoyée.<br>Une copie a été adressée à l\'adresse mail que vous avez renseigné.<p>
					<button type="button" onclick="mw.mgwHome()" > retour à la page d\'accueil</button>';
				$this->done = true;
			}
			else {
				$count = 0;
				if (isset($_COOKIE['mgw_accountRequestBlocked'])){
					$count = $_COOKIE['mgw_accountRequestBlocked'];
				}
				if ($count > 4){
					$this->mess = '
						<p id="mgw-accountrequest-ok" class="mgw-accountrequest">Vous avez dépassé le nombre d\'essais autorisés.<p>
						<button type="button" onclick="mw.mgwHome()" > retour à la page d\'accueil</button>';
					$this->done = true;
				}
				else {
					$count += 1;
					$this->mess = '
						<br>
						<i style="color:red;">
						Il y a une erreur... si vous n\'êtes pas un robot, merci de recommencer !
						</i>';
					setcookie('mgw_accountRequestBlocked',$count, time()+60*60);
				}
			}
		}

		// affichage du formulaire
		if (!$this->done) {
	    $this->form[1] = '
	      <form name="form1" action="' . $_SERVER['PHP_SELF'] . '" method="post" id="mgw-accountrequest-form" class="mgw-accountrequest">
					<table>
						<tr><td id="institution-label" style="vertical-align:top;">Institution de rattachement:&nbsp;&nbsp;&nbsp;</td>
							<td onclick="mw.accountRequest()">
								<input type="radio" id="lyon1"  name="institution" value="lyon1"  '.$post_lyon1.' ><label for="lyon1" >Université Lyon 1</label><br>
								<input type="radio" id="adepul" name="institution" value="adepul" '.$post_adepul.'><label for="adepul">Adepul</label><br>
								<input type="radio" id="autre"  name="institution" value="autre"  '.$post_autre.'><label for="adepul">Autre</label>
							</td>
						</tr>
					</table>';
			$this->form[2] = '
					<table class="mgw-hidden" style="display:none;">';
			if ($post1){
				$this->form[2] .= '
				 <fieldset id="captcha"><i>'.$key.'</i><br>
	    			<legend>Je ne suis pas un robot:</legend>
					 <input type="text" name="captchaResponse" id="captchaResponse" type="text" />
					 <input type="text" name="captchaKey" value="'.$key.'" id="captchaKey" type="text" hidden/>
				 </fieldset>';
			}
			$this->form[2] .= '
		        <tr><td><label for="nom"	 >Nom    </label></td><td><input type="text"  name="nom"    value="'.$post_nom.'"    required></td></tr>
		        <tr><td><label for="prenom">Prénom </label></td><td><input type="text"  name="prenom" value="'.$post_prenom.'" required></td></tr>
		        <tr><td><label for="email" >Email  </label></td><td><input type="email" name="email"  value="'.$post_email.'"  required></td></tr>

		        <tr><td class="mgw-tr-formateur"></td><td><input type="text" name="formateur" value="'.$post_formateur.'" class="mgw-tr-formateur"                    ></tr></td>
		        <tr><td class="mgw-tr-year"     ></td><td><input type="text" name="year"      value="'.$post_year.'"      class="mgw-tr-year"  size="4" maxlength="4" ></tr></td>
		      </table>

					<div class="mgw-hidden" id="mgw-accountrequest-comment" style="display:none;">
	 	       <p id="mgw-p-comment">Commentaires<br>
		       	<textarea id="mgw-textarea-comment" name="comment" rows="5" cols="80">'.$post_comment.'</textarea>
					 </p>
				 </div>
		      <input type="submit" value="Envoyer la demande" name="accountRequest"  class="mgw-hidden" style="display:none;"/>
	      </form>';
		}
		return true;
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub The subpage string argument (if any).
	 *  [[Special:HelloWorld/subpage]].
	 */
	public function execute( $sub ) {

		$out = $this->getOutput();
		$out->addModules('ext.mgwiki-dev-specialaccountrequest');
		$out->setPageTitle( $this->msg( 'specialaccountrequest-title' ) );

		if (!$this->done){
			$out->addHTML($this->form[1]);
			$out->addWikiTextAsContent($this->msg( 'specialaccountrequest-intro-lyon' ));
			$out->addWikiTextAsContent($this->msg( 'specialaccountrequest-intro-adepul' ));
			$out->addWikiTextAsContent($this->msg( 'specialaccountrequest-intro-autre' ));
		  $out->addHTML($this->mess);
      $out->addHTML($this->form[2]);
    }
		else {
			$out->addHTML($this->mess);
		}
  }

	/**
	 * groupe auquel se rapporte cette page spéciale
	 */
	protected function getGroupName() {
		return 'mgwiki';
	}
}
