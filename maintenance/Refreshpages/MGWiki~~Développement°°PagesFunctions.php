== Version ==
MGWiki 1.0

==Classe==
 [[MGWiki:Développement/Utilities|MediaWiki\Extension\MGWiki\Utilities]]\PagesFunctions

==Fonctions publiques statiques==
=====getPageFromAny ( $target )=====
 @param Title|string|int $target
 
 @return WikiPage|null

=====edit ( $target, $summary, $user, $content = null, $create_new = false )=====
 @param int|string $target (int)page_id|(string)page_title
 @param string $summary
 @param User $user
 @param string $content
 
 @return MGWStatus

=====getPageTemplateInfos ( $page, $template, $fields, $archive = false, $multiple = false )=====
 @param WikiPage|Title|string|int $page
 @param string $template : nom du modèle
 @param array $fields : champs recherchés [ 'champs' => 'champ du modèle' ]
 @param bool $archive
 
 @return array [ "field" => data, ... ]|null

=====updatePageTemplateInfos ( $target, $template, $data, $inline = false, $append = true, $null = false )=====
 @param WikiPage|string|int $page|$page_fullname|$page_id
 @param string $template : nom du modèle
 @param array $data [ 'field' => 'value',... ]
 @param bool $inline = retours à la ligne entre chaque argument si false
 @param bool $append = ajout du modèle en début de page si true, fin si false
 @param bool $null (false) true = retourne null en l'absence de modif
 
 @return string ( $content )

=====makeTemplate( $template, $data, $inline )=====
 @param string $template
 @param array $data
 @param bool $inline
 
 @return string

=====updateTemplateInfos ( $content, $template, $data, $inline = false, $append = true, $delete = false )=====
 @param string $content (wikitexte)
 @param string $template : nom du modèle
 @param array $data [ 'field' => 'value',... ]
 @param bool $inline = retours à la ligne entre chaque argument si false
 @param bool $append = ajout du modèle en début de page si true, fin si false
 @param bool $delete = suppression du champs du modèle
 
 @return string ( $content )

=====parseTemplates ( $content, $multiple = false )=====
 @param string $content
 @param bool $multiple
 
 @return array|null