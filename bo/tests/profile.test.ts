import { describe, expect, it } from 'vitest';
import { adminProfileCopy } from '../src/lib/modules/admins/profile';

describe('Admins self profile copy', () => {
  it('has an independent title in both interface languages', () => {
    expect(adminProfileCopy('fr').title).toBe('Mon profil');
    expect(adminProfileCopy('en').title).toBe('My profile');
  });
});
