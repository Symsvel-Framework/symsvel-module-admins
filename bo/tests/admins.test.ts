import { describe, expect, it } from 'vitest';
import { moduleId } from '../src/lib/modules/admins';
import { invitationCopy, isAssignableBoRole, canMaintainBoAdmin } from '../src/lib/modules/admins/invitations';

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
  it('protects the developer and current administrator from UI mutations', () => {
    const base = { id: 2, role_code: 'ROLE_ADMIN', email: 'other@example.test', enabled: true, invitation_pending: false, email_verified: true, created_at: '2026-11-16' };
    expect(canMaintainBoAdmin(base, 1)).toBe(true);
    expect(canMaintainBoAdmin(base, 2)).toBe(false);
    expect(canMaintainBoAdmin({ ...base, role_code: 'ROLE_DEV' }, 1)).toBe(false);
  });
  it('provides localized labels without hardcoding messages in Svelte', () => {
    expect(invitationCopy('fr-FR').send).toBe('Envoyer l’invitation');
    expect(invitationCopy('en-GB').send).toBe('Send invitation');
    expect(invitationCopy(null).send).toBe('Envoyer l’invitation');
    expect(invitationCopy('es-ES').send).toBe('Envoyer l’invitation');
  });
});

import { boNavigation } from '../src/lib/modules/admins/navigation';

describe('admins BO navigation declaration', () => {
  it('links to the ACL-protected read-only endpoint', () => {
    expect(boNavigation.path).toBe('/admins');
    expect(boNavigation.probe).toBe('/apibo/admins');
    expect(boNavigation.action).toBe('list');
    expect(boNavigation.resource).toBe('admins');
  });
});
