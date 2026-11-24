import { generatedTranslation } from '../../i18n/generated-translations';

export type BoAdminAccount = {
  id: number;
  email: string;
  role_code: string;
  language: string | null;
  enabled: boolean;
  invitation_pending: boolean;
  email_verified: boolean;
  created_at: string;
};
export type BoAdminAccountsResponse = { data: BoAdminAccount[] };
export function canMaintainBoAdmin(account: BoAdminAccount, currentId: number | null): boolean {
  return account.id !== currentId && account.role_code !== 'ROLE_DEV';
}

export type PendingInvitation = {
  id: number;
  email: string;
  role_code: string;
  language: string | null;
  enabled: boolean;
  created_at: string;
};
export type PendingInvitationsResponse = { data: PendingInvitation[] };
export type InvitationCreateResponse = { id: number; status: 'invited' };
export type AssignableRole = { code: string; label: string };
export type AssignableRolesResponse = { data: AssignableRole[] };
export function roleIsSelectable(code: string, roles: readonly AssignableRole[]): boolean {
  return isAssignableBoRole(code) && roles.some((role) => role.code === code);
}

export function isAssignableBoRole(role: string): boolean {
  return /^ROLE_[A-Z][A-Z0-9_]*$/.test(role) && role !== 'ROLE_DEV' && role !== 'ROLE_USER';
}

const copy = {
  fr: {
    emailExists: "Cette adresse e-mail est déjà utilisée par un administrateur.",
    invitationPending: "Une invitation existe déjà pour cette adresse e-mail. Consultez les invitations en attente pour la renvoyer si elle est active.",
    roleMissing: "Le rôle sélectionné n’existe plus. Actualisez la liste des rôles.",
    languageUnavailable: "La langue principale du site n’est pas configurée ou active. Vérifiez le module Languages.",
    languageInvalid: "La langue sélectionnée n’est plus disponible. Actualisez la liste des langues.",
    invitationNotPending: "Ce compte ne dispose plus d’une invitation active. Actualisez la liste des invitations.",
    invalidRequest: "Les informations saisies sont invalides. Vérifiez les champs du formulaire.",
    conflict: "L’opération est en conflit avec l’état actuel des données. Actualisez la liste.",
    title: 'Invitations administrateurs', dashboard: 'Tableau de bord',
    heading: 'Inviter un administrateur',
    intro: 'Un lien permettra au destinataire de créer son mot de passe et de confirmer son adresse.',
    email: 'Adresse e-mail', role: 'Rôle',
    roleHint: 'Seuls les rôles existants que vous avez le droit d’attribuer sont proposés.',
    chooseRole: 'Choisir un rôle', noRoles: 'Aucun rôle attribuable. Vérifiez les rôles et vos ACL.',
    accountLanguage: 'Langue du compte', locale: 'Langue de l’invitation', fr: 'Français', en: 'Anglais',
    send: 'Envoyer l’invitation', sending: 'Envoi…',
    pending: 'Invitations en attente', reload: 'Actualiser', loading: 'Chargement…',
    empty: 'Aucune invitation en attente.', date: 'Créée le', state: 'État',
    active: 'En attente', disabled: 'Désactivé',
    resend: 'Renvoyer', success: 'Invitation envoyée.', resent: 'Invitation renvoyée.',
    invalidRole: 'Saisissez un rôle BO valide.',
    forbidden: 'Action non autorisée.', unavailable: 'Invitation indisponible.',
    failure: 'Opération impossible.', listForbidden: 'Liste inaccessible avec vos droits.',
    search: 'Rechercher', searchHint: 'Adresse, rôle…',
    accounts: 'Comptes administrateurs', edit: 'Modifier', cancel: 'Annuler', save: 'Enregistrer',
    delete: 'Supprimer', confirmDelete: 'Supprimer définitivement cet administrateur ?',
    deleted: 'Administrateur supprimé.', updated: 'Administrateur mis à jour.',
    noAccounts: 'Aucun administrateur.', verified: 'Confirmé', unverified: 'Non confirmé',
    status: 'Statut', management: 'Gestion', protected: 'Compte protégé',
    session: 'Session expirée. Reconnectez-vous.', verifying: 'Vérification de la session…'
  },
  en: {
    emailExists: "This email address is already used by an administrator.",
    invitationPending: "An invitation already exists for this email address. Check pending invitations to resend it if active.",
    roleMissing: "The selected role no longer exists. Refresh the role list.",
    languageUnavailable: "The site's primary language is not configured or active. Check the Languages module.",
    languageInvalid: "The selected language is no longer available. Refresh the language list.",
    invitationNotPending: "This account no longer has an active invitation. Refresh the invitation list.",
    invalidRequest: "The submitted information is invalid. Check the form fields.",
    conflict: "The operation conflicts with the current data. Refresh the list.",
    title: 'Administrator invitations', dashboard: 'Dashboard',
    heading: 'Invite an administrator',
    intro: 'A link will let the recipient set a password and verify their email address.',
    email: 'Email address', role: 'Role',
    roleHint: 'Only existing roles you may assign are available.',
    chooseRole: 'Choose a role', noRoles: 'No assignable roles. Check Roles and your ACLs.',
    accountLanguage: 'Account language', locale: 'Invitation language', fr: 'French', en: 'English',
    send: 'Send invitation', sending: 'Sending…',
    pending: 'Pending invitations', reload: 'Refresh', loading: 'Loading…',
    empty: 'No pending invitations.', date: 'Created', state: 'Status',
    active: 'Pending', disabled: 'Disabled',
    resend: 'Resend', success: 'Invitation sent.', resent: 'Invitation resent.',
    invalidRole: 'Enter a valid BO role code.',
    forbidden: 'Permission denied.', unavailable: 'Invitation unavailable.',
    failure: 'Operation failed.', listForbidden: 'You cannot view invitations.',
    search: 'Search', searchHint: 'Email, role…',
    accounts: 'Administrator accounts', edit: 'Edit', cancel: 'Cancel', save: 'Save',
    delete: 'Delete', confirmDelete: 'Permanently delete this administrator?',
    deleted: 'Administrator deleted.', updated: 'Administrator updated.',
    noAccounts: 'No administrators.', verified: 'Verified', unverified: 'Unverified',
    status: 'Status', management: 'Management', protected: 'Protected account',
    session: 'Session expired. Please sign in.', verifying: 'Checking session…'
  }
};
export type InvitationCopy = (typeof copy)['fr'];
export function invitationCopy(language: string | null): InvitationCopy {
  const base = language?.toLowerCase().split('-')[0] === 'en' ? copy.en : copy.fr;
  return Object.fromEntries(Object.entries(base).map(([key, value]) => [
    key, generatedTranslation(language, 'bo.admins.' + key) ?? value
  ])) as InvitationCopy;
}

/** Interpret only known API codes, never raw server exception messages. */
export function invitationErrorMessage(status: number, code: string | undefined, t: InvitationCopy): string {
  if (status === 403) return t.forbidden;
  if (status === 409) {
    switch (code) {
      case 'admin_email_exists': return t.emailExists;
      case 'admin_invitation_pending': return t.invitationPending;
      case 'admin_role_missing': return t.roleMissing;
      case 'admin_language_unavailable': return t.languageUnavailable;
      case 'admin_invitation_not_pending': return t.invitationNotPending;
      case 'invitation_unavailable': return t.unavailable;
      default: return t.conflict;
    }
  }
  if (status === 400) return code === 'admin_language_invalid' ? t.languageInvalid : t.invalidRequest;
  if (status === 404 && code === 'invitation_unavailable') return t.unavailable;
  return t.failure;
}
