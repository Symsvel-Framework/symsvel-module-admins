# Admins

Documentation développeur.

## Setup initial en deux phases (en cours)

La commande `symsvel:admins:bootstrap:stdin` est réservée au Setup en état `database_ready`. Elle reçoit une seule charge JSON `{"email":"…","password":"…"}` sur son entrée standard, calcule le hash Argon2id en mémoire et délègue à `InitialAdministratorProvisioner`. Ni argument de commande, ni fichier temporaire ne doivent contenir les identifiants. Le processus appelant doit fermer stdin après écriture, contrôler le code de sortie et ne jamais journaliser la charge. La commande historique `symsvel:admins:bootstrap` reste disponible temporairement pour le parcours actuel jusqu'à la bascule coordonnée Back/Tooling.

La commande directe n'est pas encore raccordée au Wizard ; ne pas considérer le chantier comme terminé avant tests automatisés, CI et crash-tests XT.

La commande directe est explicitement enregistrée dans `AdminsBundle::loadExtension`, car ce bundle n'utilise pas l'auto-découverte globale des services. Un test vérifie le nom de commande exposé. La validation bout en bout depuis le Wizard reste en attente.

Le test de garde vérifie que la commande refuse l'exécution en l'absence d'un état Setup valide, sans exposer le chemin local dans la sortie. Ce test ne remplace pas un test d'intégration avec Doctrine et une base migrée.

Reprise de la phase administrateur : le provisionnement direct accepte uniquement un compte `ROLE_DEV` actif déjà créé avec le même e-mail, un mot de passe vérifié par `password_verify` et aucun autre administrateur en base. Le sel Argon2id changeant à chaque calcul, la comparaison de chaînes de hash ne convient pas pour cette reprise. Le provisionnement historique à partir du hash préparé reste inchangé pendant la migration.

Tests unitaires du provisionnement direct : création initiale, reprise avec mot de passe identique sans réécriture du hash, refus d'un autre mot de passe, refus si plusieurs administrateurs existent et refus de créer un deuxième compte. `AdminRepository` reste un dépôt Doctrine standard, désormais non final pour permettre son double de test sans base réelle ; les tests de migrations et d'intégration BDD demeurent nécessaires.

Avant les builds finaux, Tooling doit appeler `symsvel:admins:bootstrap:verify` : cette commande de lecture seule exige l'état `database_ready` et exactement un administrateur actif de rôle `ROLE_DEV`. Elle n'accepte aucun identifiant en argument et n'effectue aucune écriture. La vérification n'atteste pas de la validité d'une session utilisateur ; elle garantit uniquement le prérequis structurel de finalisation du Setup.
