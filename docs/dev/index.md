# Admins

Documentation développeur.

## Setup initial en deux phases (en cours)

La commande `symsvel:admins:bootstrap:stdin` est réservée au Setup en état `database_ready`. Elle reçoit une seule charge JSON `{"email":"…","password":"…"}` sur son entrée standard, calcule le hash Argon2id en mémoire et délègue à `InitialAdministratorProvisioner`. Ni argument de commande, ni fichier temporaire ne doivent contenir les identifiants. Le processus appelant doit fermer stdin après écriture, contrôler le code de sortie et ne jamais journaliser la charge. La commande historique `symsvel:admins:bootstrap` reste disponible temporairement pour le parcours actuel jusqu'à la bascule coordonnée Back/Tooling.

La commande directe n'est pas encore raccordée au Wizard ; ne pas considérer le chantier comme terminé avant tests automatisés, CI et crash-tests XT.

La commande directe est explicitement enregistrée dans `AdminsBundle::loadExtension`, car ce bundle n'utilise pas l'auto-découverte globale des services. Un test vérifie le nom de commande exposé. La validation bout en bout depuis le Wizard reste en attente.
