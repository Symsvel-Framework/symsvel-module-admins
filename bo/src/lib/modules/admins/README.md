# Admins (bo)

Point d'intégration SvelteKit du module. Importer les composants et services ici ; aucune route applicative n'est imposée.

## Écran d'invitations administrateurs (16/11/2026)

La route `bo/src/routes/admins/+page.svelte` devient l'interface BO de gestion des invitations, synchronisée par Tooling sous `#lib/modules/admins/routes/admins/+page.svelte` et exposée via le relais généré `/admins`. Elle utilise **uniquement** `currentBo()` et `boApi()` du BO hôte pour la session et les appels API. Le module ne stocke jamais ses propres tokens.

Parcours : `GET /apibo/admins/invitations` (liste limitée aux 100 dernières), `POST /apibo/admins/invitations` (adresse, rôle explicite, langue FR/EN), `POST /apibo/admins/{id}/invitations/resend` (renvoi d'un lien). Les autorisations sont vérifiées exclusivement côté serveur par Auth et ACLs. La lecture peut être refusée même si l'utilisateur peut inviter. Le renvoi est indisponible pour les comptes désactivés.

Les libellés de l'écran résident dans `bo/src/lib/modules/admins/invitations.ts`, pas dans la vue Svelte. Ils suivent la langue d'interface BO et possèdent un repli EN. Les validations visuelles des rôles ne remplacent pas la vérification Roles côté serveur. Tests Vitest de la normalisation des codes et des textes FR/EN inclus.

Validation requise : **build du BO assemblé après synchronisation**, contrôle Svelte/TypeScript/Prettier, tests navigateur et crash-tests locaux réels. La CI PHP du dépôt ne valide pas l'exécution Svelte.

### Résolution effective des imports Svelte (16/11/2026)

Le `svelte-check` du BO hôte ne résout pas les alias `#lib` et `$lib` depuis les pages modules copiées par Tooling avec la configuration TypeScript actuelle. La page utilise donc des **imports relatifs à sa destination synchronisée** sous `src/lib/modules/…/routes/`, suivant le modèle déjà opérationnel des pages de connexion et dashboard du BO. Le relais de route généré par Tooling demeure inchangé. Le CI `bo-integration` teste la résolution dans le vrai BO assemblé.
