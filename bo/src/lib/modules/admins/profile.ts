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
    "passwordLength": "Respectez les critères du nouveau mot de passe.",
    "passwordSuccess": "Mot de passe modifié. Reconnexion nécessaire.",
    "singleLanguage": "Une seule langue est disponible pour ce projet.",
    "emailChangeTitle": "Changer mon adresse e-mail",
    "newEmail": "Nouvelle adresse e-mail",
    "emailCurrentPassword": "Mot de passe actuel",
    "sendEmailConfirmation": "Envoyer le lien de confirmation",
    "emailSent": "Un lien de confirmation a été envoyé à la nouvelle adresse. L'adresse actuelle reste active jusqu'à validation.",
    "emailChangeInvalid": "Vérifiez l'adresse et le mot de passe actuel, puis réessayez.",
    "emailChangeUnavailable": "Impossible d'envoyer le lien pour le moment. Vérifiez les paramètres Mails.",
    "emailChangeHelp": "Votre ancienne adresse reste active jusqu'à la confirmation de la nouvelle.",
    "emailVerificationTitle": "Confirmer la nouvelle adresse",
    "emailVerificationBusy": "Confirmation en cours…",
    "emailVerificationSuccess": "Nouvelle adresse confirmée. Reconnectez-vous avec celle-ci.",
    "emailVerificationFailed": "Lien invalide, expiré ou déjà utilisé.",
    "backToLogin": "Revenir à la connexion"
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
    "passwordLength": "Meet the new password requirements.",
    "passwordSuccess": "Password changed. Sign in again.",
    "singleLanguage": "Only one language is available for this project.",
    "emailChangeTitle": "Change my email address",
    "newEmail": "New email address",
    "emailCurrentPassword": "Current password",
    "sendEmailConfirmation": "Send confirmation link",
    "emailSent": "A confirmation link was sent to the new address. Your current email remains active until you confirm.",
    "emailChangeInvalid": "Check your email address and current password, then try again.",
    "emailChangeUnavailable": "Cannot send the confirmation link right now. Check mail settings.",
    "emailChangeHelp": "Your current email remains active until the new one has been confirmed.",
    "emailVerificationTitle": "Confirm new email address",
    "emailVerificationBusy": "Confirming…",
    "emailVerificationSuccess": "New address confirmed. Sign in again with your new email.",
    "emailVerificationFailed": "Invalid, expired or already used confirmation link.",
    "backToLogin": "Back to login"
  }
};
export type AdminProfileCopy = typeof labels.fr;
export function adminProfileCopy(locale: string | null): AdminProfileCopy {
  const source = locale?.toLowerCase().startsWith('en') ? labels.en : labels.fr;
  return Object.fromEntries(Object.entries(source).map(([key, value]) =>
    [key, generatedTranslation(locale, 'bo.admins.profile.' + key) ?? value])) as AdminProfileCopy;
}
