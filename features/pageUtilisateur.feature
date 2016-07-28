# language: fr

Fonctionnalité: Modifier les informations d’un utilisateur
  Sur les pages utilisateur, quelques renseignements doivent
  être enregistrés et synchronisés avec MediaWik.

  Scénario: Martine modifie son adresse de courriel
    Étant donné Martine une utilisatrice de niveau 1
    Quand elle édite sa page utilisateur
    Et inscrit test@example.org dans le champ « E-mail »
    Et enregistre la page
    Alors l’adresse de courriel enregistrée dans ses préférences est test@example.org

  Scénario: Martine modifie son statut de « interne » à « médecin »
    Étant donné Martine une utilisatrice de niveau 1
    Quand elle édite sa page utilisateur
    Et clique sur le statut « médecin »
    Et enregistre la page
    Alors sa page indique le statut « médecin »
    Et elle appartient au groupe utilisateur « médecin »

  Scénario: Martine tente de modifier la page de Alexandre
    Étant donné Martine une utilisatrice de niveau 1
    Quand elle édite la page utilisateur de Alexandre
    Alors l’accès en édition lui est refusé

  Scénario: Martine tente de modifier son statut additionnel
    Étant donné Martine une utilisatrice de niveau 1
    Quand elle clique sur le statut additionnel « tuteur »
    Alors le statut additionnel ne change pas

  Scénario: Alexandre modifie l’adresse de courriel de Martine
    Étant donné Alexandre un utilisateur de niveau 2
    Quand il édite la page utilisateur de Martine
    Et inscrit test@example.org dans le champ « E-mail »
    Et enregistre la page
    Alors l’adresse de courriel enregistrée dans les préférences de Martine est test@example.org

  Scénario: Alexandre modifie le statut de Martine de « interne » à « médecin »
    Étant donné Alexandre un utilisateur de niveau 2
    Quand il édite la page utilisateur de Martine
    Et clique sur le statut « médecin »
    Et enregistre la page
    Alors la page de Martine indique le statut « médecin »
    Et elle appartient au groupe utilisateur « médecin »

  Scénario: Alexandre modifie le statut additionnel de Martine
    Étant donné Alexandre un utilisateur de niveau 2
    Quand il édite la page utilisateur de Martine
    Et clique sur le statut additionnel « tuteur »
    Et enregistre la page
    Alors la page de Martine indique le statut additionnel « tuteur »
    Et elle appartient au groupe utilisateur « tuteur »

  Scénario: Alexandre retire le statut additionnel de Martine
    Étant donné Alexandre un utilisateur de niveau 2
    Quand il édite la page utilisateur de Martine
    Et clique sur le statut additionnel « aucun »
    Et enregistre la page
    Alors la page de Martine n’indique aucun statut additionnel
    Mais elle n’appartient pas aux groupes utilisateur « tuteur » ou « modérateur »

