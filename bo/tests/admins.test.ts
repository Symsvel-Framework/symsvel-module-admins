import { describe, expect, it } from 'vitest';
import { moduleId } from '../src/lib/modules/admins';
import { invitationCopy, isAssignableBoRole } from '../src/lib/modules/admins/invitations';

describe('admins module', () => {
  it('exports its identifier', () => { expect(moduleId).toBe('admins'); });
  it('rejects dev and app roles from BO invitations', () => {
    expect(isAssignableBoRole('ROLE_ADMIN')).toBe(true);
    expect(isAssignableBoRole('ROLE_SUPER_ADMIN')).toBe(true);
    expect(isAssignableBoRole('ROLE_EDITOR')).toBe(true);
    for (const value of ['ROLE_DEV', 'ROLE_USER', '', 'ROLE_', 'ROLE_editor']) {
      expect(isAssignableBoRole(value)).toBe(false);
    }
  });
  it('provides localized labels without hardcoding messages in Svelte', () => {
    expect(invitationCopy('fr-FR').send).toBe('Envoyer l’invitation');
    expect(invitationCopy('en-GB').send).toBe('Send invitation');
    expect(invitationCopy(null).send).toBe('Send invitation');
  });
});
