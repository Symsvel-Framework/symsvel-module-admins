import { generatedTranslation } from '../../i18n/generated-translations';

export type DocumentationEntry = {
  id: string; scope: 'dev' | 'admin' | 'user'; source: string;
  owner: string; title: string; file: string;
};
export type DocumentationPage = DocumentationEntry & { markdown: string };
export type DocumentationIndex = { data: DocumentationEntry[]; published: boolean };
export type DocumentationPageResponse = { data: DocumentationPage };

const fallback = {
  fr: {
    title: 'Documentation', intro: 'Guides et références des modules installés.',
    refresh: 'Actualiser', loading: 'Chargement de la documentation…',
    pageLoading: 'Chargement du document…', empty: 'Aucun document disponible.',
    unpublished: 'Documentation non assemblée. Un mainteneur doit exécuter symsvel documentation:sync.',
    failed: 'Impossible de consulter la documentation.', expired: 'Session expirée. Reconnectez-vous.',
    admin: 'Administration', user: 'Utilisation', dev: 'Développement',
    select: 'Sélectionner un document'
  },
  en: {
    title: 'Documentation', intro: 'Guides and references for installed modules.',
    refresh: 'Refresh', loading: 'Loading documentation…',
    pageLoading: 'Loading document…', empty: 'No documentation available.',
    unpublished: 'Documentation has not been assembled. A maintainer must run symsvel documentation:sync.',
    failed: 'Cannot load documentation.', expired: 'Session expired. Please sign in again.',
    admin: 'Administration', user: 'User guides', dev: 'Development',
    select: 'Select a document'
  }
};
export type DocumentationCopy = typeof fallback.fr;
export function documentationCopy(locale: string | null): DocumentationCopy {
  const source = locale?.toLowerCase().startsWith('en') ? fallback.en : fallback.fr;
  return Object.fromEntries(Object.entries(source).map(([key, value]) =>
    [key, generatedTranslation(locale, 'bo.admins.documentation.' + key) ?? value])) as DocumentationCopy;
}

export type MarkdownBlock =
  | { kind: 'heading'; level: 1 | 2 | 3; text: string }
  | { kind: 'paragraph' | 'quote' | 'code'; text: string }
  | { kind: 'list'; items: string[] };

/**
 * Display source Markdown as Svelte-escaped text. Never interpret raw HTML or
 * Markdown extensions capable of injecting JavaScript into the BO.
 */
export function markdownBlocks(source: string): MarkdownBlock[] {
  const result: MarkdownBlock[] = [];
  const lines = source.replace(/\r\n?/g, '\n').split('\n');
  let paragraph: string[] = [];
  let list: string[] = [];
  let code: string[] = [];
  let fenced = false;
  function flushParagraph(): void {
    if (paragraph.length) result.push({ kind: 'paragraph', text: paragraph.join(' ') });
    paragraph = [];
  }
  function flushList(): void {
    if (list.length) result.push({ kind: 'list', items: list });
    list = [];
  }
  for (const line of lines) {
    if (/^\s*\x60\x60\x60/.test(line)) {
      flushParagraph();
      flushList();
      if (fenced) {
        result.push({ kind: 'code', text: code.join('\n') });
        code = [];
      }
      fenced = !fenced;
      continue;
    }
    if (fenced) {
      code.push(line);
      continue;
    }
    const heading = /^(#{1,3})\s+(.+)$/.exec(line);
    if (heading) {
      flushParagraph();
      flushList();
      result.push({ kind: 'heading', level: heading[1].length as 1 | 2 | 3, text: heading[2] });
      continue;
    }
    const item = /^\s*(?:[-*]|\d+\.)\s+(.+)$/.exec(line);
    if (item) {
      flushParagraph();
      list.push(item[1]);
      continue;
    }
    if (line.trim() === '') {
      flushParagraph();
      flushList();
      continue;
    }
    if (line.startsWith('> ')) {
      flushParagraph();
      flushList();
      result.push({ kind: 'quote', text: line.slice(2) });
      continue;
    }
    flushList();
    paragraph.push(line.trim());
  }
  if (code.length) result.push({ kind: 'code', text: code.join('\n') });
  flushParagraph();
  flushList();
  return result;
}

/** Allow only HTTP(S) external links, never arbitrary URI schemes or HTML. */
export function safeInlineLinks(text: string): { label: string; href?: string }[] {
  const parts: { label: string; href?: string }[] = [];
  const pattern = /\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/g;
  let cursor = 0;
  for (const match of text.matchAll(pattern)) {
    const start = match.index ?? 0;
    if (start > cursor) parts.push({ label: text.slice(cursor, start) });
    parts.push({ label: match[1], href: match[2] });
    cursor = start + match[0].length;
  }
  if (cursor < text.length) parts.push({ label: text.slice(cursor) });
  return parts;
}
