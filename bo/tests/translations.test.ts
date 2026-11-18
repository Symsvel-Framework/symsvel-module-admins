import { describe, expect, it } from 'vitest';
import fr from '../src/lib/translations/fr';
import en from '../src/lib/translations/en';

describe('Admins dictionaries', () => {
  it('keeps FR and EN keys aligned', () => {
    expect(Object.keys(fr).sort()).toEqual(Object.keys(en).sort());
    expect(fr.account.administrator).toBe('Administrateur');
    expect(en.account.administrator).toBe('Administrator');
    expect(fr.heading).toBe('Inviter un administrateur');
  });
});
