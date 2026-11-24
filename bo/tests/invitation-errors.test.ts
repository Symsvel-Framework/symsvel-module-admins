import { describe, expect, it } from 'vitest';
import { invitationCopy, invitationErrorMessage } from '../src/lib/modules/admins/invitations';

describe('administrator invitation error messages', () => {
  it('maps known conflicts in French and English', () => {
    for (const lang of ['fr', 'en']) {
      const t = invitationCopy(lang);
      expect(invitationErrorMessage(409, 'admin_email_exists', t)).toBe(t.emailExists);
      expect(invitationErrorMessage(409, 'admin_invitation_pending', t)).toBe(t.invitationPending);
      expect(invitationErrorMessage(409, 'admin_role_missing', t)).toBe(t.roleMissing);
      expect(invitationErrorMessage(409, 'admin_language_unavailable', t)).toBe(t.languageUnavailable);
      expect(invitationErrorMessage(409, 'admin_invitation_not_pending', t)).toBe(t.invitationNotPending);
      expect(invitationErrorMessage(400, 'admin_language_invalid', t)).toBe(t.languageInvalid);
      expect(invitationErrorMessage(409, 'unknown', t)).toBe(t.conflict);
      expect(invitationErrorMessage(403, 'admin_email_exists', t)).toBe(t.forbidden);
      expect(invitationErrorMessage(500, 'SQL error with credentials', t)).toBe(t.failure);
    }
  });
});
