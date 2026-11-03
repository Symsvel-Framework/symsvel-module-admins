import { describe, expect, it } from 'vitest';
import { moduleId } from '../src/lib/modules/symsvel-module-admins';

describe('symsvel-module-admins module', () => {
  it('exports its identifier', () => {
    expect(moduleId).toBe('symsvel-module-admins');
  });
});
