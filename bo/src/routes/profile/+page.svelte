<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import Alert from '../../../../components/Alert.svelte';
  import Button from '../../../../components/Button.svelte';
  import Dropdown from '../../../../components/Dropdown.svelte';
  import { languageFlag } from '../../../../components/dropdown';
  import { api, ApiError } from '../../../../api/client';
  import { boApi } from '../../../../api/session';
  import { uiLanguage } from '../../../../api/locales';
  import {
    adminProfileCopy, type AdminSelfProfile, type AdminSelfProfileResponse, type ProfileLanguage
  } from '../../profile';

  let profile = $state<AdminSelfProfile | null>(null);
  let languages = $state<ProfileLanguage[]>([]);
  let selected = $state('');
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
      const [account, choices] = await Promise.all([
        boApi<AdminSelfProfileResponse>('/apibo/admins/profile'),
        api<{ data: ProfileLanguage[] }>('/apiopen/languages/account-options', { public: true })
      ]);
      profile = account.data;
      languages = Array.isArray(choices.data) ? choices.data : [];
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
</script>

<svelte:head><title>{t.title} | SymSvel BO</title></svelte:head>
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
        <label class="field-label">{t.language}</label>
        <Dropdown value={selected} {options} label={t.language}
          disabled={saving || languages.length < 2} onChange={(value) => { selected = value; }} />
        <Button type="submit" loading={saving} disabled={!selected || selected === profile.language || !options.length}>
          {t.save}
        </Button>
      </form>
      <p class="profile-note">{t.readonly}</p>
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
  .profile-note { margin: 0; font-size: 0.9rem; }
</style>
