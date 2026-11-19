import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { documentationCopy, markdownBlocks, safeInlineLinks } from '../src/lib/modules/admins/documentation';

describe('Admins authenticated documentation viewer', () => {
  it('provides localized copy without hardcoded documentation body', () => {
    expect(documentationCopy('fr').title).toBe('Documentation');
    expect(documentationCopy('en').dev).toBe('Development');
  });
  it('parses headings, lists, code and paragraphs safely as text blocks', () => {
    const fence = String.fromCharCode(96).repeat(3);
    const blocks = markdownBlocks('# Guide\n\nHello **world**\n\n- first\n- second\n\n' +
      fence + 'html\n<script>alert(1)</script>\n' + fence);
    expect(blocks.map((block) => block.kind)).toEqual(['heading', 'paragraph', 'list', 'code']);
    expect(blocks.at(-1)).toEqual({ kind: 'code', text: '<script>alert(1)</script>' });
    expect(safeInlineLinks('[good](https://example.test) [bad](javascript:alert(1))')).toEqual([
      { label: 'good', href: 'https://example.test' },
      { label: ' [bad](javascript:alert(1))' }
    ]);
  });
  it('uses only the authenticated BO client and never injects raw Markdown as HTML', () => {
    const source = readFileSync(new URL('../src/lib/modules/admins/routes/documentation/+page.svelte', import.meta.url), 'utf8');
    expect(source).toContain("'/apibo/admins/documentation'");
    expect(source).toContain("'/apibo/admins/documentation/page?id='");
    expect(source).toContain('boApi<DocumentationIndex>');
    expect(source).not.toContain('{@html');
    expect(source).not.toContain('public: true');
    expect(source).not.toContain('fetch(');
  });
});
