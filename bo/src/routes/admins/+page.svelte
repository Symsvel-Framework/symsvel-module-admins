<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { ApiError } from '#lib/api/client';
  import { uiLanguage } from '#lib/api/locales';
  import { boApi, currentBo } from '#lib/api/session';
  import {
    invitationCopy, isAssignableBoRole,
    type PendingInvitation, type PendingInvitationsResponse, type InvitationCreateResponse
  } from '#lib/modules/admins/invitations';

  let ready = $state(false);
  let email = $state('');
  let role = $state('');
  let locale = $state<'fr' | 'en'>('fr');
  let busy = $state(false);
  let refreshing = $state(false);
  let resending = $state<number | null>(null);
  let invitations = $state<PendingInvitation[]>([]);
  let error = $state('');
  let listError = $state('');
  let message = $state('');
  const language = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(invitationCopy(language));

  function explain(cause: unknown): string {
    if (cause instanceof ApiError) {
      if (cause.status === 401) {
        void goto(resolve('/login'), { replaceState: true });
        return t.session;
      }
      if (cause.status === 403) return t.forbidden;
      if (cause.status === 409) return t.unavailable;
    }
    return t.failure;
  }

  async function refresh(): Promise<void> {
    refreshing = true;
    listError = '';
    try {
      const result = await boApi<PendingInvitationsResponse>('/apibo/admins/invitations');
      invitations = Array.isArray(result?.data) ? result.data : [];
    } catch (cause) {
      listError = cause instanceof ApiError && cause.status === 403 ? t.listForbidden : explain(cause);
    } finally {
      refreshing = false;
    }
  }

  onMount(() => {
    let mounted = true;
    void currentBo().then((identity) => {
      if (!mounted) return;
      if (!identity) {
        void goto(resolve('/login'), { replaceState: true });
        return;
      }
      ready = true;
      locale = language.toLowerCase().startsWith('fr') ? 'fr' : 'en';
      void refresh();
    });
    return () => { mounted = false; };
  });

  async function invite(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (busy) return;
    error = '';
    message = '';
    if (!isAssignableBoRole(role.trim())) {
      error = t.invalidRole;
      return;
    }
    busy = true;
    try {
      await boApi<InvitationCreateResponse>('/apibo/admins/invitations', {
        method: 'POST',
        body: JSON.stringify({ email: email.trim(), role_code: role.trim(), locale })
      });
      email = '';
      role = '';
      message = t.success;
      await refresh();
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
    }
  }

  async function resend(id: number): Promise<void> {
    if (resending !== null) return;
    error = '';
    message = '';
    resending = id;
    try {
      await boApi<{status: string}>(`/apibo/admins/${id}/invitations/resend`, {
        method: 'POST', body: JSON.stringify({ locale })
      });
      message = t.resent;
    } catch (cause) {
      error = explain(cause);
    } finally {
      resending = null;
    }
  }
</script>

<svelte:head><title>{t.title} | SymSvel BO</title></svelte:head>
{#if ready}
  <div class="bo-shell">
    <aside class="bo-sidebar">
      <div class="bo-brand">SymSvel BO</div>
      <nav aria-label={t.dashboard}><a href={resolve('/dashboard')}>{t.dashboard}</a></nav>
    </aside>
    <div class="bo-main">
      <header class="bo-topbar"><strong>{t.title}</strong></header>
      <main class="bo-content invitations">
        <section class="panel">
          <h1>{t.heading}</h1><p>{t.intro}</p>
          {#if error}<p class="notice error" role="alert">{error}</p>{/if}
          {#if message}<p class="notice success" role="status">{message}</p>{/if}
          <form onsubmit={invite}>
            <label for="new-admin-email">{t.email}</label>
            <input id="new-admin-email" type="email" bind:value={email} autocomplete="email" maxlength="254" required />
            <label for="new-admin-role">{t.role}</label>
            <input id="new-admin-role" type="text" bind:value={role} placeholder="ROLE_ADMIN" maxlength="100" aria-describedby="role-help" required />
            <small id="role-help">{t.roleHint}</small>
            <label for="invitation-locale">{t.locale}</label>
            <select id="invitation-locale" bind:value={locale}>
              <option value="fr">{t.fr}</option><option value="en">{t.en}</option>
            </select>
            <button type="submit" disabled={busy}>{busy ? t.sending : t.send}</button>
          </form>
        </section>
        <section class="panel">
          <div class="section-heading">
            <h2>{t.pending}</h2>
            <button class="secondary" type="button" onclick={refresh} disabled={refreshing}>{t.reload}</button>
          </div>
          {#if listError}<p class="notice error" role="alert">{listError}</p>{/if}
          {#if refreshing}<p role="status">{t.loading}</p>{/if}
          {#if !refreshing && !listError && invitations.length === 0}<p>{t.empty}</p>{/if}
          {#if invitations.length > 0}
            <div class="table-container">
              <table>
                <thead><tr><th>{t.email}</th><th>{t.role}</th><th>{t.date}</th><th>{t.state}</th><th></th></tr></thead>
                <tbody>
                  {#each invitations as item (item.id)}
                    <tr>
                      <td>{item.email}</td><td><code>{item.role_code}</code></td>
                      <td>{new Date(item.created_at).toLocaleDateString(language)}</td>
                      <td>{item.enabled ? t.active : t.disabled}</td>
                      <td>
                        <button class="secondary" type="button"
                          disabled={!item.enabled || resending !== null}
                          onclick={() => resend(item.id)}>{resending === item.id ? t.sending : t.resend}</button>
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </section>
      </main>
    </div>
  </div>
{:else}
  <main class="auth-shell"><p role="status">{t.verifying}</p></main>
{/if}

<style>
  .invitations { max-width: 1200px; margin: auto; display: grid; gap: 22px; }
  .bo-sidebar nav { margin-top: 26px; }
  .bo-sidebar a { color: #dce3ec; }
  .panel { background: #fff; padding: 28px; border: 1px solid #e2e7ee; border-radius: 14px; }
  h1, h2 { margin-top: 0; }
  p, small { color: #667085; }
  form { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr); gap: 12px 20px; margin-top: 26px; }
  label { font-weight: 600; }
  form label, form input, form select, form small { grid-column: 1 / -1; }
  input, select { padding: 12px; border-radius: 8px; border: 1px solid #ccd4df; font: inherit; }
  button { border-radius: 8px; border: 0; padding: 11px 16px; background: #1e293b; color: #fff; font: inherit; cursor: pointer; }
  button:disabled { cursor: not-allowed; opacity: 0.6; }
  .secondary { background: #fff; color: #1e293b; border: 1px solid #ccd4df; }
  .section-heading { display: flex; align-items: center; justify-content: space-between; }
  .notice { padding: 10px 14px; border-radius: 8px; }
  .error { background: #fff1f1; color: #9d2626; }
  .success { background: #eefaf2; color: #236747; }
  .table-container { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; text-align: left; }
  th, td { padding: 13px 10px; border-bottom: 1px solid #e2e7ee; }
  th { color: #667085; font-size: .8rem; }
  @media (max-width: 700px) { form { grid-template-columns: 1fr; } .panel { padding: 18px; } }
</style>
