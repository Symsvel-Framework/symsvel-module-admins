export type BoAdminAccount = {
  id: number;
  email: string;
  role_code: string;
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
  enabled: boolean;
  created_at: string;
};
export type PendingInvitationsResponse = { data: PendingInvitation[] };
export type InvitationCreateResponse = { id: number; status: 'invited' };

export function isAssignableBoRole(role: string): boolean {
  return /^ROLE_[A-Z][A-Z0-9_]*$/.test(role) && role !== 'ROLE_DEV' && role !== 'ROLE_USER';
}

const copy = {
  fr: {
    title: 'Invitations administrateurs', dashboard: 'Tableau de bord',
    heading: 'Inviter un administrateur',
    intro: 'Un lien permettra au destinataire de créer son mot de passe et de confirmer son adresse.',
    email: 'Adresse e-mail', role: 'Code du rôle',
    roleHint: 'Saisissez un rôle existant dans Roles, par exemple ROLE_ADMIN.',
    locale: 'Langue de l’invitation', fr: 'Français', en: 'Anglais',
    send: 'Envoyer l’invitation', sending: 'Envoi…',
    pending: 'Invitations en attente', reload: 'Actualiser', loading: 'Chargement…',
    empty: 'Aucune invitation en attente.', date: 'Créée le', state: 'État',
    active: 'En attente', disabled: 'Désactivé',
    resend: 'Renvoyer', success: 'Invitation envoyée.', resent: 'Invitation renvoyée.',
    invalidRole: 'Saisissez un rôle BO valide.',
    forbidden: 'Action non autorisée.', unavailable: 'Invitation indisponible.',
    failure: 'Opération impossible.', listForbidden: 'Liste inaccessible avec vos droits.',
    accounts: 'Comptes administrateurs', edit: 'Modifier', cancel: 'Annuler', save: 'Enregistrer',
    delete: 'Supprimer', confirmDelete: 'Supprimer définitivement cet administrateur ?',
    deleted: 'Administrateur supprimé.', updated: 'Administrateur mis à jour.',
    noAccounts: 'Aucun administrateur.', verified: 'Confirmé', unverified: 'Non confirmé',
    status: 'Statut', management: 'Gestion', protected: 'Compte protégé',
    session: 'Session expirée. Reconnectez-vous.', verifying: 'Vérification de la session…'
  },
  en: {
    title: 'Administrator invitations', dashboard: 'Dashboard',
    heading: 'Invite an administrator',
    intro: 'A link will let the recipient set a password and verify their email address.',
    email: 'Email address', role: 'Role code',
    roleHint: 'Enter an existing Roles code, for example ROLE_ADMIN.',
    locale: 'Invitation language', fr: 'French', en: 'English',
    send: 'Send invitation', sending: 'Sending…',
    pending: 'Pending invitations', reload: 'Refresh', loading: 'Loading…',
    empty: 'No pending invitations.', date: 'Created', state: 'Status',
    active: 'Pending', disabled: 'Disabled',
    resend: 'Resend', success: 'Invitation sent.', resent: 'Invitation resent.',
    invalidRole: 'Enter a valid BO role code.',
    forbidden: 'Permission denied.', unavailable: 'Invitation unavailable.',
    failure: 'Operation failed.', listForbidden: 'You cannot view invitations.',
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
  return language?.toLowerCase().split('-')[0] === 'fr' ? copy.fr : copy.en;
}
