import { generatedTranslation } from '../../i18n/generated-translations';

export type AdminSelfProfile = {
  id: number;
  email: string;
  role_code: string;
  language: string | null;
  email_verified: boolean;
};
export type AdminSelfProfileResponse = { data: AdminSelfProfile };
export type ProfileLanguage = { iso_code: string; name: string; primary: boolean };
const labels = {
  "fr": {
    "title": "Mon profil",
    "intro": "Gérer les préférences de mon compte administrateur.",
    "email": "Adresse e-mail",
    "role": "Rôle",
    "verified": "Confirmée",
    "unverified": "À confirmer",
    "verification": "Adresse e-mail",
    "language": "Langue de mon compte",
    "save": "Enregistrer",
    "saving": "Enregistrement…",
    "saved": "Langue du compte enregistrée.",
    "loading": "Chargement du profil…",
    "error": "Impossible de charger ou d’enregistrer le profil.",
    "session": "Session expirée. Reconnectez-vous.",
    "back": "Tableau de bord",
    "readonly": "La modification de l’adresse e-mail et du mot de passe nécessite un parcours de sécurité dédié.",
    "passwordTitle": "Changer mon mot de passe",
    "currentPassword": "Mot de passe actuel",
    "newPassword": "Nouveau mot de passe",
    "confirmPassword": "Confirmer le nouveau mot de passe",
    "passwordSave": "Modifier mon mot de passe",
    "passwordInvalid": "Vérifiez le mot de passe actuel et saisissez un nouveau mot de passe différent.",
    "passwordMismatch": "Les deux nouveaux mots de passe ne correspondent pas.",
    "passwordLength": "12 caractères minimum.",
    "passwordSuccess": "Mot de passe modifié. Reconnexion nécessaire.",
    "singleLanguage": "Une seule langue est disponible pour ce projet."
  },
  "en": {
    "title": "My profile",
    "intro": "Manage my administrator account preferences.",
    "email": "Email address",
    "role": "Role",
    "verified": "Verified",
    "unverified": "Unverified",
    "verification": "Email verification",
    "language": "Account language",
    "save": "Save",
    "saving": "Saving…",
    "saved": "Account language saved.",
    "loading": "Loading profile…",
    "error": "Unable to load or save profile.",
    "session": "Session expired. Sign in again.",
    "back": "Dashboard",
    "readonly": "Changing email and password requires a dedicated security flow.",
    "passwordTitle": "Change my password",
    "currentPassword": "Current password",
    "newPassword": "New password",
    "confirmPassword": "Confirm new password",
    "passwordSave": "Change my password",
    "passwordInvalid": "Check your current password and enter a different new password.",
    "passwordMismatch": "The two new passwords do not match.",
    "passwordLength": "At least 12 characters.",
    "passwordSuccess": "Password changed. Sign in again.",
    "singleLanguage": "Only one language is available for this project."
  }
};
export type AdminProfileCopy = typeof labels.fr;
export function adminProfileCopy(locale: string | null): AdminProfileCopy {
  const source = locale?.toLowerCase().startsWith('en') ? labels.en : labels.fr;
  return Object.fromEntries(Object.entries(source).map(([key, value]) =>
    [key, generatedTranslation(locale, 'bo.admins.profile.' + key) ?? value])) as AdminProfileCopy;
}
