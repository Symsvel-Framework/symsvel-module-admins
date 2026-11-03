import { describe, expect, it } from 'vitest';
import { moduleId } from '../src/lib/modules/admins';

describe('admins module', () => {
  it('exports its identifier', () => {
    expect(moduleId).toBe('admins');
  });
});
