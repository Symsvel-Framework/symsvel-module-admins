<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import Button from '../../../../components/Button.svelte';
  import Alert from '../../../../components/Alert.svelte';
  import Modal from '../../../../components/Modal.svelte';
  import DataTable from '../../../../components/DataTable.svelte';
  import type { DataAction, DataColumn, SortOrder } from '../../../../components/data-table';
  import type { PageSize } from '../../../../components/pagination';
  import { ApiError } from '../../../../api/client';
  import { uiLanguage } from '../../../../api/locales';
  import { boApi } from '../../../../api/session';
  import { getBoShell } from '../../../../bo/context';
  import {
    invitationCopy, isAssignableBoRole, canMaintainBoAdmin,
    type PendingInvitation, type PendingInvitationsResponse, type InvitationCreateResponse,
    type BoAdminAccount, type BoAdminAccountsResponse
  } from '../../invitations';

  const shell = getBoShell();
  let activeTab = $state<'accounts' | 'invitations'>('accounts');
  let showInvite = $state(false);
  let editing = $state<BoAdminAccount | null>(null);
  let email = $state('');
  let role = $state('');
  let locale = $state<'fr' | 'en'>('fr');
  let newRole = $state('');
  let newEnabled = $state(true);
  let busy = $state(false);
  let refreshing = $state(false);
  let resending = $state<number | null>(null);
  let deleting = $state<number | null>(null);
  let invitations = $state<PendingInvitation[]>([]);
  let accounts = $state<BoAdminAccount[]>([]);
  let currentId = $state<number | null>(null);
  let error = $state('');
  let listError = $state('');
  let message = $state('');
  let search = $state('');
  let sortKey = $state<string | null>('email');
  let sortOrder = $state<SortOrder>('asc');
  let page = $state(1);
  let limit = $state<PageSize>(20);

  const language = $derived($uiLanguage ?? (typeof navigator === 'undefined' ? 'en' : navigator.language));
  const t = $derived(invitationCopy(language));

  const accountColumns = $derived<DataColumn<BoAdminAccount>[]>([
    { key: 'email', label: t.email, sortable: true },
    { key: 'role_code', label: t.role, sortable: true },
    { key: 'email_verified', label: t.status, format: (v) => v ? t.verified : t.unverified },
    { key: 'enabled', label: t.state, format: (v) => v ? t.active : t.disabled }
  ]);
  const accountActions = $derived<DataAction<BoAdminAccount>[]>([
    {
      key: 'edit', label: (row) => canMaintainBoAdmin(row, currentId) ? t.edit : t.protected,
      disabled: (row) => !canMaintainBoAdmin(row, currentId),
      onClick: (row) => startEdit(row)
    },
    {
      key: 'delete', label: t.delete,
      disabled: (row) => !canMaintainBoAdmin(row, currentId) || deleting !== null,
      onClick: (row) => { void deleteAccount(row.id); }
    }
  ]);
  const invitationColumns = $derived<DataColumn<PendingInvitation>[]>([
    { key: 'email', label: t.email, sortable: true },
    { key: 'role_code', label: t.role, sortable: true },
    { key: 'created_at', label: t.date, sortable: true,
      format: (v) => new Date(String(v)).toLocaleDateString(language) },
    { key: 'enabled', label: t.state, format: (v) => v ? t.active : t.disabled }
  ]);
  const invitationActions = $derived<DataAction<PendingInvitation>[]>([
    {
      key: 'resend', label: (row) => resending === row.id ? t.sending : t.resend,
      disabled: (row) => !row.enabled || resending !== null,
      onClick: (row) => { void resend(row.id); }
    }
  ]);

  const filteredAccounts = $derived(sortRows(
    accounts.filter((item) => !item.invitation_pending).filter((item) =>
      `${item.email} ${item.role_code}`.toLocaleLowerCase().includes(search.toLocaleLowerCase())
    )
  ));
  const filteredInvitations = $derived(sortRows(
    invitations.filter((item) =>
      `${item.email} ${item.role_code}`.toLocaleLowerCase().includes(search.toLocaleLowerCase())
    )
  ));
  const selectedRows = $derived(activeTab === 'accounts' ? filteredAccounts : filteredInvitations);
  const visibleAccounts = $derived(filteredAccounts.slice((page - 1) * limit, page * limit));
  const visibleInvitations = $derived(filteredInvitations.slice((page - 1) * limit, page * limit));

  function sortRows<T extends {email: string; role_code: string; created_at: string}>(rows: T[]): T[] {
    return [...rows].sort((a, b) => {
      const key = sortKey === 'role_code' || sortKey === 'created_at' ? sortKey : 'email';
      return a[key].localeCompare(b[key], language, { sensitivity: 'base' }) * (sortOrder === 'asc' ? 1 : -1);
    });
  }

  function selectTab(tab: 'accounts' | 'invitations'): void {
    activeTab = tab;
    search = '';
    sortKey = 'email';
    sortOrder = 'asc';
    page = 1;
  }

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
      const [pending, listed] = await Promise.all([
        boApi<PendingInvitationsResponse>('/apibo/admins/invitations'),
        boApi<BoAdminAccountsResponse>('/apibo/admins')
      ]);
      invitations = Array.isArray(pending.data) ? pending.data : [];
      accounts = Array.isArray(listed.data) ? listed.data : [];
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

  function openInvite(): void {
    email = '';
    role = '';
    locale = language.toLowerCase().startsWith('fr') ? 'fr' : 'en';
    error = '';
    showInvite = true;
  }

  async function invite(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (busy) return;
    error = '';
    message = '';
    if (!isAssignableBoRole(role.trim())) { error = t.invalidRole; return; }
    busy = true;
    try {
      await boApi<InvitationCreateResponse>('/apibo/admins/invitations', {
        method: 'POST',
        body: JSON.stringify({ email: email.trim(), role_code: role.trim(), locale })
      });
      showInvite = false;
      message = t.success;
      selectTab('invitations');
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
        method: 'POST', body: JSON.stringify({ locale: language.toLowerCase().startsWith('fr') ? 'fr' : 'en' })
      });
      message = t.resent;
      await refresh();
    } catch (cause) {
      error = explain(cause);
    } finally {
      resending = null;
    }
  }

  function startEdit(account: BoAdminAccount): void {
    if (!canMaintainBoAdmin(account, currentId)) return;
    editing = account;
    newRole = account.role_code;
    newEnabled = account.enabled;
    error = '';
    message = '';
  }

  async function updateAccount(): Promise<void> {
    if (!editing || busy) return;
    if (!isAssignableBoRole(newRole)) { error = t.invalidRole; return; }
    busy = true;
    error = '';
    message = '';
    try {
      await boApi<{status: string}>('/apibo/admins/' + editing.id, {
        method: 'PATCH', body: JSON.stringify({ role_code: newRole, enabled: newEnabled })
      });
      editing = null;
      message = t.updated;
      await refresh();
    } catch (cause) {
      error = explain(cause);
    } finally {
      busy = false;
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
      await refresh();
    } catch (cause) {
      error = explain(cause);
    } finally {
      deleting = null;
    }
  }
</script>

<svelte:head><title>{t.accounts} | SymSvel BO</title></svelte:head>
<div class="admins-page">
  <header class="page-heading">
    <div><h1>{t.accounts}</h1><p>{t.intro}</p></div>
    <Button type="button" onclick={openInvite}>{t.heading}</Button>
  </header>

  {#if message}<Alert kind="success">{message}</Alert>{/if}
  {#if error && !showInvite && !editing}<Alert kind="error">{error}</Alert>{/if}
  {#if listError}<Alert kind="error">{listError}</Alert>{/if}

  <section class="panel">
    <div class="section-heading">
      <div class="tabs" role="tablist" aria-label={t.accounts}>
        <button type="button" role="tab" id="admins-tab-accounts"
          aria-selected={activeTab === 'accounts'} aria-controls="admins-panel-accounts"
          tabindex={activeTab === 'accounts' ? 0 : -1} onclick={() => selectTab('accounts')}>
          {t.accounts} ({accounts.filter((item) => !item.invitation_pending).length})
        </button>
        <button type="button" role="tab" id="admins-tab-invitations"
          aria-selected={activeTab === 'invitations'} aria-controls="admins-panel-invitations"
          tabindex={activeTab === 'invitations' ? 0 : -1} onclick={() => selectTab('invitations')}>
          {t.pending} ({invitations.length})
        </button>
      </div>
      <Button type="button" variant="secondary" onclick={refresh} disabled={refreshing}>{t.reload}</Button>
    </div>
    {#if refreshing}<p role="status">{t.loading}</p>{/if}
    <div id="admins-panel-accounts" role="tabpanel" aria-labelledby="admins-tab-accounts" hidden={activeTab !== 'accounts'}>
      {#if activeTab === 'accounts'}
        <DataTable rows={visibleAccounts} columns={accountColumns} actions={accountActions}
          loading={refreshing} emptyMessage={t.noAccounts}
          filters={[{ key: 'search', label: t.search, type: 'search', placeholder: t.searchHint }]}
          filterValues={{ search }}
          onFilterChange={(_, value) => { search = value; page = 1; }}
          {sortKey} {sortOrder}
          onSortChange={(key, order) => { sortKey = key; sortOrder = order; page = 1; }}
          pagination={{
            page, limit, total: filteredAccounts.length,
            onPageChange: (value) => page = value,
            onLimitChange: (value) => { limit = value; page = 1; }
          }} />
      {/if}
    </div>
    <div id="admins-panel-invitations" role="tabpanel" aria-labelledby="admins-tab-invitations" hidden={activeTab !== 'invitations'}>
      {#if activeTab === 'invitations'}
        <DataTable rows={visibleInvitations} columns={invitationColumns} actions={invitationActions}
          loading={refreshing} emptyMessage={t.empty}
          filters={[{ key: 'search', label: t.search, type: 'search', placeholder: t.searchHint }]}
          filterValues={{ search }}
          onFilterChange={(_, value) => { search = value; page = 1; }}
          {sortKey} {sortOrder}
          onSortChange={(key, order) => { sortKey = key; sortOrder = order; page = 1; }}
          pagination={{
            page, limit, total: filteredInvitations.length,
            onPageChange: (value) => page = value,
            onLimitChange: (value) => { limit = value; page = 1; }
          }} />
      {/if}
    </div>
  </section>

  <Modal open={showInvite} title={t.heading} onClose={() => showInvite = false}>
    <p>{t.intro}</p>
    {#if error}<Alert kind="error">{error}</Alert>{/if}
    <form class="modal-form" onsubmit={invite}>
      <label for="new-admin-email">{t.email}</label>
      <input id="new-admin-email" type="email" bind:value={email} autocomplete="email" maxlength="254" required />
      <label for="new-admin-role">{t.role}</label>
      <input id="new-admin-role" type="text" bind:value={role} placeholder="ROLE_ADMIN" maxlength="100" aria-describedby="role-help" required />
      <small id="role-help">{t.roleHint}</small>
      <label for="invitation-locale">{t.locale}</label>
      <select id="invitation-locale" bind:value={locale}>
        <option value="fr">{t.fr}</option><option value="en">{t.en}</option>
      </select>
      <div class="modal-actions">
        <Button type="button" variant="secondary" onclick={() => showInvite = false}>{t.cancel}</Button>
        <Button type="submit" disabled={busy}>{busy ? t.sending : t.send}</Button>
      </div>
    </form>
  </Modal>

  <Modal open={editing !== null} title={t.edit} onClose={() => editing = null}>
    {#if editing}
      <p>{editing.email}</p>
      {#if error}<Alert kind="error">{error}</Alert>{/if}
      <form class="modal-form" onsubmit={(event) => { event.preventDefault(); void updateAccount(); }}>
        <label for="edit-admin-role">{t.role}</label>
        <input id="edit-admin-role" type="text" bind:value={newRole} maxlength="100" required />
        <label for="edit-admin-enabled">{t.state}</label>
        <select id="edit-admin-enabled" bind:value={newEnabled}>
          <option value={true}>{t.active}</option>
          <option value={false}>{t.disabled}</option>
        </select>
        <div class="modal-actions">
          <Button type="button" variant="secondary" onclick={() => editing = null}>{t.cancel}</Button>
          <Button type="submit" disabled={busy}>{t.save}</Button>
        </div>
      </form>
    {/if}
  </Modal>
</div>

<style>
  .admins-page { max-width: 1200px; margin: auto; display: grid; gap: 20px; }
  .page-heading, .section-heading { display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap; }
  .page-heading h1 { margin: 0; }
  .page-heading p { color: #667085; }
  .panel { background: white; padding: 24px; border: 1px solid #e2e7ee; border-radius: 14px; min-width: 0; }
  .section-heading { margin-bottom: 20px; }
  .tabs { display: flex; flex-wrap: wrap; gap: 8px; border-bottom: 1px solid #e2e7ee; }
  .tabs button { padding: 12px; border: 0; border-bottom: 3px solid transparent;
    background: transparent; font: inherit; cursor: pointer; color: #667085; }
  .tabs button[aria-selected="true"] { border-bottom-color: #2457d6; color: #18202b; font-weight: 700; }
  .tabs button:focus-visible { outline: 2px solid #2457d6; outline-offset: -2px; }
  .modal-form { display: grid; gap: 10px; margin-top: 15px; }
  .modal-form label { font-weight: 650; }
  .modal-form input, .modal-form select { width: 100%; padding: 11px; border: 1px solid #ccd4df; border-radius: 8px; font: inherit; }
  .modal-form small { color: #667085; }
  .modal-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
</style>
