<script lang="ts">
  import { onMount } from 'svelte';
  import Button from '../../../../components/Button.svelte';
  import DataTable from '../../../../components/DataTable.svelte';
  import type { DataAction, DataColumn } from '../../../../components/data-table';
  import Alert from '../../../../components/Alert.svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { ApiError } from '../../../../api/client';
  import { uiLanguage } from '../../../../api/locales';
  import { boApi } from '../../../../api/session';
  import { getBoShell } from '../../../../bo/context';
  import {
    invitationCopy, isAssignableBoRole,
    type PendingInvitation, type PendingInvitationsResponse, type InvitationCreateResponse,
    canMaintainBoAdmin, type BoAdminAccount, type BoAdminAccountsResponse
  } from '../../invitations';

  let email = $state('');
  let role = $state('');
  let locale = $state<'fr' | 'en'>('fr');
  let busy = $state(false);
  let refreshing = $state(false);
  let resending = $state<number | null>(null);
  let invitations = $state<PendingInvitation[]>([]);
  let accounts = $state<BoAdminAccount[]>([]);
  const shell = getBoShell();
  let currentId = $state<number | null>(null);
  let editing = $state<number | null>(null);
  let newRole = $state('');
  let newEnabled = $state(true);
  let saving = $state(false);
  let deleting = $state<number | null>(null);
  let error = $state('');
  let listError = $state('');
  let message = $state('');
  const language = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(invitationCopy(language));
  const invitationColumns = $derived<DataColumn<PendingInvitation>[]>([
    { key: 'email', label: t.email },
    { key: 'role_code', label: t.role },
    { key: 'created_at', label: t.date,
      format: (value) => new Date(String(value)).toLocaleDateString(language) },
    { key: 'enabled', label: t.state,
      format: (value) => value ? t.active : t.disabled }
  ]);
  const invitationActions = $derived<DataAction<PendingInvitation>[]>([
    {
      key: 'resend',
      label: (row) => resending === row.id ? t.sending : t.resend,
      disabled: (row) => !row.enabled || resending !== null,
      onClick: (row) => { void resend(row.id); }
    }
  ]);

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
      const listed = await boApi<BoAdminAccountsResponse>('/apibo/admins');
      accounts = Array.isArray(listed?.data) ? listed.data : [];
    } catch (cause) {
      listError = cause instanceof ApiError && cause.status === 403 ? t.listForbidden : explain(cause);
    } finally {
      refreshing = false;
    }
  }

  onMount(() => {
    currentId = shell.identity?.id ?? null;
    void refresh();
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

  function startEdit(account: BoAdminAccount): void {
    editing = account.id;
    newRole = account.role_code;
    newEnabled = account.enabled;
    error = '';
    message = '';
  }

  async function updateAccount(id: number): Promise<void> {
    if (saving || !isAssignableBoRole(newRole)) {
      error = t.invalidRole;
      return;
    }
    saving = true;
    error = '';
    message = '';
    try {
      await boApi<{status: string}>('/apibo/admins/' + id, {
        method: 'PATCH', body: JSON.stringify({ role_code: newRole, enabled: newEnabled })
      });
      editing = null;
      message = t.updated;
      await refresh();
    } catch (cause) {
      error = explain(cause);
    } finally {
      saving = false;
    }
  }

  async function deleteAccount(id: number): Promise<void> {
    if (deleting !== null || !window.confirm(t.confirmDelete)) return;
    deleting = id;
    error = '';
    message = '';
    try {
      await boApi<{status: string}>('/apibo/admins/' + id, { method: 'DELETE' });
      message = t.deleted;
      if (editing === id) editing = null;
      await refresh();
    } catch (cause) {
      error = explain(cause);
    } finally {
      deleting = null;
    }
  }
</script>

<svelte:head><title>{t.title} | SymSvel BO</title></svelte:head>
<div class="invitations">
        <section class="panel">
          <h1>{t.heading}</h1><p>{t.intro}</p>
          {#if error}<Alert kind="error">{error}</Alert>{/if}
          {#if message}<Alert kind="success">{message}</Alert>{/if}
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
            <Button type="submit" disabled={busy}>{busy ? t.sending : t.send}</Button>
          </form>
        </section>
        <section class="panel">
          <div class="section-heading">
            <h2>{t.pending}</h2>
            <Button variant="secondary" type="button" onclick={refresh} disabled={refreshing}>{t.reload}</Button>
          </div>
          {#if listError}<Alert kind="error">{listError}</Alert>{/if}
          {#if refreshing}<p role="status">{t.loading}</p>{/if}
          {#if !refreshing && !listError && invitations.length === 0}<p>{t.empty}</p>{/if}
          {#if invitations.length > 0}
            <DataTable
              rows={invitations}
              columns={invitationColumns}
              actions={invitationActions}
              loading={refreshing}
              emptyMessage={t.empty}
            />
          {/if}
        </section>
        <section class="panel">
          <h2>{t.accounts}</h2>
          {#if accounts.length === 0 && !refreshing && !listError}<p>{t.noAccounts}</p>{/if}
          {#if accounts.length > 0}
            <div class="table-container">
              <table>
                <thead>
                  <tr><th>{t.email}</th><th>{t.role}</th><th>{t.status}</th><th>{t.management}</th></tr>
                </thead>
                <tbody>
                  {#each accounts as account (account.id)}
                    <tr>
                      <td>{account.email}<small>{account.email_verified ? t.verified : t.unverified}</small></td>
                      <td>
                        {#if editing === account.id}
                          <label class="sr-only" for={'role-' + account.id}>{t.role}</label>
                          <input id={'role-' + account.id} type="text" bind:value={newRole}
                            maxlength="100" required />
                        {:else}
                          <code>{account.role_code}</code>
                        {/if}
                      </td>
                      <td>
                        {#if editing === account.id}
                          <label class="sr-only" for={'enabled-' + account.id}>{t.status}</label>
                          <select id={'enabled-' + account.id} bind:value={newEnabled}>
                            <option value={true}>{t.active}</option>
                            <option value={false}>{t.disabled}</option>
                          </select>
                        {:else}
                          {account.enabled ? t.active : t.disabled}
                        {/if}
                      </td>
                      <td>
                        {#if canMaintainBoAdmin(account, currentId)}
                          {#if editing === account.id}
                            <Button type="button" disabled={saving || deleting !== null}
                              onclick={() => updateAccount(account.id)}>{saving ? t.sending : t.save}</Button>
                            <Button type="button" variant="secondary" onclick={() => editing = null}>{t.cancel}</Button>
                          {:else}
                            <Button type="button" variant="secondary" onclick={() => startEdit(account)}>{t.edit}</Button>
                            <Button type="button" variant="secondary" disabled={deleting !== null}
                              onclick={() => deleteAccount(account.id)}>{t.delete}</Button>
                          {/if}
                        {:else}
                          <small>{t.protected}</small>
                        {/if}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          {/if}
        </section>
      </div>

<style>
  .invitations { max-width: 1200px; margin: auto; display: grid; gap: 22px; }
  .panel { background: #fff; padding: 28px; border: 1px solid #e2e7ee; border-radius: 14px; }
  h1, h2 { margin-top: 0; }
  p, small { color: #667085; }
  form { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(180px, 1fr); gap: 12px 20px; margin-top: 26px; }
  label { font-weight: 600; }
  form label, form input, form select, form small { grid-column: 1 / -1; }
  input, select { padding: 12px; border-radius: 8px; border: 1px solid #ccd4df; font: inherit; }
  .section-heading { display: flex; align-items: center; justify-content: space-between; }
  .table-container { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; text-align: left; }
  th, td { padding: 13px 10px; border-bottom: 1px solid #e2e7ee; }
  th { color: #667085; font-size: .8rem; }
  td small { display: block; margin-top: 5px; }
  .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
  @media (max-width: 700px) { form { grid-template-columns: 1fr; } .panel { padding: 18px; } }
</style>
