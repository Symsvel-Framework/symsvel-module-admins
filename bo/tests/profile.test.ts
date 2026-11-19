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
  expect(source).toContain("'/apiopen/languages/account-options'");
  expect(source).toContain('public: true');
  expect(source).not.toContain("'/apibo/languages/account-options'");
});
