<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import Alert from '../../../../components/Alert.svelte';
  import Button from '../../../../components/Button.svelte';
  import { ApiError } from '../../../../api/client';
  import { boApi } from '../../../../api/session';
  import { uiLanguage } from '../../../../api/locales';
  import {
    documentationCopy, markdownBlocks, safeInlineLinks,
    type DocumentationEntry, type DocumentationIndex,
    type DocumentationPage, type DocumentationPageResponse
  } from '../../documentation';

  let entries = $state<DocumentationEntry[]>([]);
  let selected = $state('');
  let content = $state<DocumentationPage | null>(null);
  let published = $state(false);
  let loading = $state(true);
  let pageLoading = $state(false);
  let error = $state('');
  const locale = $derived($uiLanguage ?? 'fr');
  const t = $derived(documentationCopy(locale));
  const blocks = $derived(content ? markdownBlocks(content.markdown) : []);

  function explain(cause: unknown): string {
    if (cause instanceof ApiError && cause.status === 401) {
      void goto(resolve('/login'), { replaceState: true });
      return t.expired;
    }
    return t.failed;
  }
  async function openPage(id: string): Promise<void> {
    if (!id || pageLoading) return;
    pageLoading = true;
    error = '';
    selected = id;
    content = null;
    try {
      const response = await boApi<DocumentationPageResponse>(
        '/apibo/admins/documentation/page?id=' + encodeURIComponent(id)
      );
      content = response.data;
    } catch (cause) {
      error = explain(cause);
    } finally {
      pageLoading = false;
    }
  }
  async function load(): Promise<void> {
    loading = true;
    error = '';
    content = null;
    try {
      const response = await boApi<DocumentationIndex>('/apibo/admins/documentation');
      entries = Array.isArray(response.data) ? response.data : [];
      published = response.published === true;
      const current = entries.some((item) => item.id === selected) ? selected : (entries[0]?.id ?? '');
      if (current) await openPage(current);
      else selected = '';
    } catch (cause) {
      entries = [];
      published = false;
      error = explain(cause);
    } finally {
      loading = false;
    }
  }
  onMount(() => { void load(); });
</script>

{#snippet inline(text: string)}
  {#each safeInlineLinks(text) as part, index (index)}
    {#if part.href}
      <!-- eslint-disable-next-line svelte/no-navigation-without-resolve -->
      <a href={part.href} rel="noopener noreferrer" target="_blank">{part.label}</a>
    {:else}
      {part.label}
    {/if}
  {/each}
{/snippet}

<svelte:head><title>{t.title} | SymSvel BO</title></svelte:head>
<section class="docs-page">
  <header class="docs-head">
    <div><h1>{t.title}</h1><p>{t.intro}</p></div>
    <Button type="button" variant="secondary" onclick={load} disabled={loading}>{t.refresh}</Button>
  </header>
  {#if error}<Alert kind="error">{error}</Alert>{/if}
  {#if loading}<p role="status">{t.loading}</p>{/if}
  {#if !loading && !published}
    <Alert kind="info">{t.unpublished}</Alert>
  {:else if !loading && entries.length === 0}
    <p role="status">{t.empty}</p>
  {/if}
  {#if entries.length > 0}
    <div class="docs-layout">
      <nav class="docs-menu" aria-label={t.select}>
        {#each ['admin', 'user', 'dev'] as scope (scope)}
          {#if entries.some((entry) => entry.scope === scope)}
            <h2>{scope === 'admin' ? t.admin : scope === 'user' ? t.user : t.dev}</h2>
            {#each entries.filter((entry) => entry.scope === scope) as item (item.id)}
              <button type="button" class:active={selected === item.id}
                aria-current={selected === item.id ? 'page' : undefined}
                disabled={pageLoading} onclick={() => { void openPage(item.id); }}>
                <span>{item.title}</span>
                <small>{item.owner}</small>
              </button>
            {/each}
          {/if}
        {/each}
      </nav>
      <article class="docs-reader" aria-live="polite">
        {#if pageLoading}<p role="status">{t.pageLoading}</p>{/if}
        {#if content}
          {#each blocks as block, index (index)}
            {#if block.kind === 'heading'}
              {#if block.level === 1}<h2>{@render inline(block.text)}</h2>
              {:else if block.level === 2}<h3>{@render inline(block.text)}</h3>
              {:else}<h4>{@render inline(block.text)}</h4>{/if}
            {:else if block.kind === 'paragraph'}
              <p>{@render inline(block.text)}</p>
            {:else if block.kind === 'quote'}
              <blockquote>{@render inline(block.text)}</blockquote>
            {:else if block.kind === 'code'}
              <pre><code>{block.text}</code></pre>
            {:else if block.kind === 'list'}
              <ul>{#each block.items as item, i (i)}<li>{@render inline(item)}</li>{/each}</ul>
            {/if}
          {/each}
        {/if}
      </article>
    </div>
  {/if}
</section>

<style>
  .docs-page { display: grid; gap: 18px; max-width: 1440px; margin: 0 auto; }
  .docs-head { display: flex; gap: 20px; justify-content: space-between; align-items: center; flex-wrap: wrap; }
  .docs-head h1 { margin: 0; }
  .docs-head p { color: var(--bo-muted); }
  .docs-layout { display: grid; grid-template-columns: minmax(210px, 290px) minmax(0, 1fr); gap: 16px; }
  .docs-menu, .docs-reader { background: var(--bo-surface); border: 1px solid var(--bo-border); border-radius: 12px; padding: 18px; min-width: 0; }
  .docs-menu { display: flex; flex-direction: column; gap: 5px; max-height: 75vh; overflow: auto; }
  .docs-menu h2 { font-size: .76rem; text-transform: uppercase; letter-spacing: .08em; margin: 20px 5px 8px; color: var(--bo-muted); }
  .docs-menu button { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; border: 0; border-radius: 8px; background: transparent; color: var(--bo-text); font: inherit; padding: 10px; text-align: left; cursor: pointer; }
  .docs-menu button:hover, .docs-menu button.active { background: var(--bo-surface-muted); }
  .docs-menu button.active { border-left: 3px solid var(--bo-primary); }
  .docs-menu button:focus-visible { outline: 2px solid var(--bo-focus); outline-offset: 2px; }
  .docs-menu small { color: var(--bo-muted); font-size: .75rem; }
  .docs-reader { max-height: 75vh; overflow: auto; overflow-wrap: anywhere; line-height: 1.7; }
  .docs-reader :global(h2), .docs-reader :global(h3), .docs-reader :global(h4) { margin: 20px 0 10px; line-height: 1.3; }
  .docs-reader :global(h2:first-child) { margin-top: 0; }
  .docs-reader :global(p) { margin: 8px 0 14px; }
  .docs-reader :global(pre) { white-space: pre-wrap; background: var(--bo-surface-muted); border-radius: 8px; padding: 12px; overflow-x: auto; font-size: .86rem; }
  .docs-reader :global(blockquote) { border-left: 3px solid var(--bo-primary); padding-left: 14px; color: var(--bo-muted); margin: 14px 0; }
  .docs-reader :global(a) { color: var(--bo-primary); text-decoration: underline; }
  @media (max-width: 760px) { .docs-layout { grid-template-columns: 1fr; } .docs-menu { max-height: 280px; } .docs-reader { max-height: none; } }
</style>
