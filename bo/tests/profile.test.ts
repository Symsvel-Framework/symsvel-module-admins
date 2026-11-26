import { describe, expect, it } from 'vitest';
import { adminProfileCopy } from '../src/lib/modules/admins/profile';

describe('Admins self profile copy', () => {
  it('has an independent title in both interface languages', () => {
    expect(adminProfileCopy('fr').title).toBe('Mon profil');
    expect(adminProfileCopy('en').title).toBe('My profile');
  });
});

import { readFileSync } from 'node:fs';

it('does not require account-management ACLs to list personal language preferences', () => {
  const source = readFileSync(new URL('../src/routes/profile/+page.svelte', import.meta.url), 'utf8');
  expect(source).toContain("'/apiopen/languages'");
  expect(source).toContain('public: true');
  expect(source).not.toContain("'/apibo/languages/account-options'");
});


it('offers email and password changes alongside language, never another account target', () => {
  const source = readFileSync(new URL('../src/routes/profile/+page.svelte', import.meta.url), 'utf8');
  expect(source).toContain("'/apibo/admins/profile/password'");
  expect(source).toContain("'/apibo/admins/profile/email-change'");
  expect(source).toContain('current_password');
  expect(source).toContain('new_email');
  expect(source).toContain('new_password');
  expect(source).toContain('logoutBo()');
  expect(source).not.toContain('target_admin_id');
});

it('provides a public confirmation page in the correct session context', () => {
  const source = readFileSync(new URL('../src/routes/verify-email-change/+page.svelte', import.meta.url), 'utf8');
  expect(source).toContain("'/apibo/auth/profile/email-change/complete'");
  expect(source).toContain('public: true');
  expect(source).toContain('history.replaceState');
});

it('uses the public language catalog and renders independent responsive panels only with a real language choice', () => {
  const source = readFileSync(new URL('../src/routes/profile/+page.svelte', import.meta.url), 'utf8');
  expect(source).toContain("'/apiopen/languages'");
  expect(source).not.toContain("'/apiopen/languages/account-options'");
  expect(source).toContain('{#if languages.length > 1}');
  expect(source).toContain('data-profile-details');
  expect(source).toContain('data-profile-password');
  expect(source).not.toContain('class="auth-shell"');
});
