# Admins (bo)

Point d'intégration SvelteKit du module. Importer les composants et services ici ; aucune route applicative n'est imposée.

## Écran d'invitations administrateurs (16/11/2026)

La route `bo/src/routes/admins/+page.svelte` devient l'interface BO de gestion des invitations, synchronisée par Tooling sous `#lib/modules/admins/routes/admins/+page.svelte` et exposée via le relais généré `/admins`. Elle utilise **uniquement** `currentBo()` et `boApi()` du BO hôte pour la session et les appels API. Le module ne stocke jamais ses propres tokens.

Parcours : `GET /apibo/admins/invitations` (liste limitée aux 100 dernières), `POST /apibo/admins/invitations` (adresse, rôle explicite, langue FR/EN), `POST /apibo/admins/{id}/invitations/resend` (renvoi d'un lien). Les autorisations sont vérifiées exclusivement côté serveur par Auth et ACLs. La lecture peut être refusée même si l'utilisateur peut inviter. Le renvoi est indisponible pour les comptes désactivés.

Les libellés de l'écran résident dans `bo/src/lib/modules/admins/invitations.ts`, pas dans la vue Svelte. Ils suivent la langue d'interface BO et possèdent un repli EN. Les validations visuelles des rôles ne remplacent pas la vérification Roles côté serveur. Tests Vitest de la normalisation des codes et des textes FR/EN inclus.

Validation requise : **build du BO assemblé après synchronisation**, contrôle Svelte/TypeScript/Prettier, tests navigateur et crash-tests locaux réels. La CI PHP du dépôt ne valide pas l'exécution Svelte.

### Résolution des imports (16/11/2026)

Les sources Svelte des modules utilisent l'alias **natif SvelteKit** `$lib` pour importer les clients et helpers du BO. L'alias `#lib` du relais de route reste généré par Tooling, mais ne résout pas les imports TypeScript directs dans les pages lors de `svelte-check`. La CI du BO assemblé couvre cette différence ; aucun changement n'est nécessaire dans la logique métier Auth ou Admins.
