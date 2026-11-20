/** BO navigation belongs to the module; authorization remains in its PHP controller. */
export const boNavigation = {
  path: '/admins',
  label: { fr: 'Administrateurs', en: 'Administrators' },
  resource: 'admins',
  action: 'list',
  probe: '/apibo/admins'
} as const;
