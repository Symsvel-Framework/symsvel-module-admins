<script lang="ts">
  import { getProjectBrand } from '../../lib/branding';
  const brandName = getProjectBrand();
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import Alert from '../../../../components/Alert.svelte';
  import Button from '../../../../components/Button.svelte';
  import Dropdown from '../../../../components/Dropdown.svelte';
  import { languageFlag } from '../../../../components/dropdown';
  import { api, ApiError } from '../../../../api/client';
  import { boApi, logoutBo } from '../../../../api/session';
  import { uiLanguage } from '../../../../api/locales';
  import {
    adminProfileCopy, type AdminSelfProfile, type AdminSelfProfileResponse, type ProfileLanguage
  } from '../../profile';

  let profile = $state<AdminSelfProfile | null>(null);
  let languages = $state<ProfileLanguage[]>([]);
  let selected = $state('');
  let newEmail = $state('');
  let emailPassword = $state('');
  let emailBusy = $state(false);
  let emailError = $state('');
  let emailNotice = $state('');
  let currentPassword = $state('');
  let newPassword = $state('');
  let confirmPassword = $state('');
  let passwordBusy = $state(false);
  let passwordError = $state('');
  let passwordNotice = $state('');
  let loading = $state(true);
  let saving = $state(false);
  let error = $state('');
  let notice = $state('');
  const locale = $derived($uiLanguage ?? 'fr');
  const t = $derived(adminProfileCopy(locale));
  const options = $derived(languages.map((item) => ({
    value: item.iso_code, label: item.name,
    code: item.iso_code.toUpperCase(), icon: languageFlag(item.iso_code)
  })));

  function explain(cause: unknown): string {
    if (cause instanceof ApiError && cause.status === 401) {
      void goto(resolve('/login'), { replaceState: true });
      return t.session;
    }
    return t.error;
  }
  async function load(): Promise<void> {
    loading = true;
    error = '';
    try {
      const account = await boApi<AdminSelfProfileResponse>('/apibo/admins/profile');
      profile = account.data;
      try {
        const choices = await api<{ data: ProfileLanguage[] }>('/apiopen/languages/account-options', { public: true });
        languages = Array.isArray(choices.data) ? choices.data : [];
      } catch {
        // The personal account remains accessible if the language catalog is unavailable.
        languages = [];
      }
      selected = profile.language ?? languages.find((value) => value.primary)?.iso_code ?? '';
    } catch (cause) {
      error = explain(cause);
    } finally {
      loading = false;
    }
  }
  onMount(() => { void load(); });

  async function save(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (saving || !profile || !options.some((item) => item.value === selected)) return;
    saving = true;
    notice = '';
    error = '';
    try {
      const updated = await boApi<AdminSelfProfileResponse>('/apibo/admins/profile', {
        method: 'PATCH',
        body: JSON.stringify({ language: selected })
      });
      profile = updated.data;
      notice = t.saved;
    } catch (cause) {
      error = explain(cause);
    } finally {
      saving = false;
    }
  }


  async function changeEmail(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (emailBusy || !profile) return;
    emailError = '';
    emailNotice = '';
    if (!newEmail.includes('@') || newEmail.trim().toLowerCase() === profile.email.toLowerCase()) {
      emailError = t.emailChangeInvalid;
      return;
    }
    emailBusy = true;
    try {
      await boApi<{ status: string }>('/apibo/admins/profile/email-change', {
        method: 'POST',
        body: JSON.stringify({
          new_email: newEmail.trim(),
          current_password: emailPassword,
          locale: locale.toLowerCase().startsWith('fr') ? 'fr' : 'en'
        })
      });
      newEmail = '';
      emailPassword = '';
      emailNotice = t.emailSent;
    } catch (cause) {
      emailError = cause instanceof ApiError && cause.status === 403
        ? t.emailChangeInvalid : t.emailChangeUnavailable;
    } finally {
      emailBusy = false;
    }
  }

  async function changePassword(event: SubmitEvent): Promise<void> {
    event.preventDefault();
    if (passwordBusy) return;
    passwordError = '';
    passwordNotice = '';
    if (newPassword !== confirmPassword) {
      passwordError = t.passwordMismatch;
      return;
    }
    if (newPassword.length < 12 || newPassword.length > 1024 || newPassword === currentPassword) {
      passwordError = t.passwordLength;
      return;
    }
    passwordBusy = true;
    try {
      await boApi<{ status: string }>('/apibo/admins/profile/password', {
        method: 'POST',
        body: JSON.stringify({ current_password: currentPassword, new_password: newPassword })
      });
      currentPassword = '';
      newPassword = '';
      confirmPassword = '';
      passwordNotice = t.passwordSuccess;
      await logoutBo();
      await goto(resolve('/login'), { replaceState: true });
    } catch (cause) {
      passwordError = cause instanceof ApiError && cause.status === 403 ? t.passwordInvalid : explain(cause);
    } finally {
      passwordBusy = false;
    }
  }

</script>

<svelte:head><title>{t.title} | {$brandName} BO</title></svelte:head>
<section class="profile-page">
  <header>
    <h1>{t.title}</h1>
    <p>{t.intro}</p>
  </header>
  {#if error}<Alert kind="error">{error}</Alert>{/if}
  {#if notice}<Alert kind="success">{notice}</Alert>{/if}
  {#if loading}<p role="status">{t.loading}</p>{/if}
  {#if profile}
    <section class="profile-card">
      <dl>
        <div><dt>{t.email}</dt><dd>{profile.email}</dd></div>
        <div><dt>{t.role}</dt><dd>{profile.role_code}</dd></div>
        <div><dt>{t.verification}</dt><dd>{profile.email_verified ? t.verified : t.unverified}</dd></div>
      </dl>
      <form onsubmit={save}>
        <p class="field-label">{t.language}</p>
        <Dropdown value={selected} {options} label={t.language}
          disabled={saving || languages.length < 2} onChange={(value) => { selected = value; }} />
        {#if languages.length > 1}
          <Button type="submit" loading={saving} disabled={!selected || selected === profile.language || !options.length}>
            {t.save}
          </Button>
        {/if}
      </form>
      {#if languages.length < 2}
        <p class="profile-note">{t.singleLanguage}</p>
      {/if}

      <div class="profile-separator"></div>
      <form onsubmit={changeEmail}>
        <h2>{t.emailChangeTitle}</h2>
        {#if emailError}<Alert kind="error">{emailError}</Alert>{/if}
        {#if emailNotice}<Alert kind="success">{emailNotice}</Alert>{/if}
        <label for="admin-email-new">{t.newEmail}</label>
        <input id="admin-email-new" type="email" autocomplete="email" bind:value={newEmail}
          required maxlength="254" disabled={emailBusy} />
        <label for="admin-email-password">{t.emailCurrentPassword}</label>
        <input id="admin-email-password" type="password" autocomplete="current-password"
          bind:value={emailPassword} required disabled={emailBusy} />
        <p class="profile-note">{t.emailChangeHelp}</p>
        <Button type="submit" loading={emailBusy} disabled={emailBusy || !newEmail || !emailPassword}>
          {t.sendEmailConfirmation}
        </Button>
      </form>
      <div class="profile-separator"></div>
      <form onsubmit={changePassword} autocomplete="off">
        <h2>{t.passwordTitle}</h2>
        {#if passwordError}<Alert kind="error">{passwordError}</Alert>{/if}
        {#if passwordNotice}<Alert kind="success">{passwordNotice}</Alert>{/if}
        <label for="admin-password-current">{t.currentPassword}</label>
        <input id="admin-password-current" type="password" autocomplete="current-password"
          bind:value={currentPassword} required disabled={passwordBusy} />
        <label for="admin-password-next">{t.newPassword}</label>
        <input id="admin-password-next" type="password" autocomplete="new-password"
          bind:value={newPassword} required minlength="12" maxlength="1024" disabled={passwordBusy} />
        <label for="admin-password-confirm">{t.confirmPassword}</label>
        <input id="admin-password-confirm" type="password" autocomplete="new-password"
          bind:value={confirmPassword} required minlength="12" maxlength="1024" disabled={passwordBusy} />
        <p class="profile-note">{t.passwordLength}</p>
        <Button type="submit" loading={passwordBusy}
          disabled={passwordBusy || !currentPassword || newPassword.length < 12 || newPassword !== confirmPassword}>
          {t.passwordSave}
        </Button>
      </form>
    </section>
  {/if}
  <a href={resolve('/dashboard')}>{t.back}</a>
</section>

<style>
  .profile-page { max-width: 780px; display: grid; gap: 18px; margin: auto; color: var(--bo-text); }
  .profile-page h1 { margin: 0; }
  .profile-page header p, .profile-note { color: var(--bo-muted); }
  .profile-card { background: var(--bo-surface); border: 1px solid var(--bo-border); border-radius: 14px; padding: 24px; display: grid; gap: 22px; }
  dl { display: grid; gap: 12px; margin: 0; }
  dl div { display: grid; gap: 5px; }
  dt, .field-label { color: var(--bo-muted); font-weight: 650; }
  dd { margin: 0; overflow-wrap: anywhere; }
  form { display: grid; gap: 12px; justify-items: stretch; }
  form h2 { font-size: 1.05rem; margin: 0 0 4px; }
  form label { font-weight: 650; }
  form input { width: 100%; box-sizing: border-box; padding: 11px 13px;
    border-radius: 8px; border: 1px solid var(--bo-border); background: var(--bo-surface-muted);
    color: var(--bo-text); font: inherit; }
  .profile-separator { border-top: 1px solid var(--bo-border); }
  .profile-note { margin: 0; font-size: 0.9rem; }
</style>
