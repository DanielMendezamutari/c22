export default [
  {
    title: 'Dashboard en Vivo (1 min)',
    to: { path: '/dashboard/super-admin' },
    icon: { icon: 'ri-dashboard-line' },
  },
  {
    heading: 'AUDITORÍA CON IA',
  },
  {
    title: 'Recaudaciones & Vouchers',
    to: { path: '/auditoria/recaudaciones' },
    icon: { icon: 'ri-file-search-line' },
    badgeContent: 'IA',
    badgeClass: 'bg-primary',
  },
  {
    title: 'Conciliación Triangulada',
    to: { path: '/auditoria/conciliacion-triangulada' },
    icon: { icon: 'ri-scales-3-line' },
    badgeContent: 'POS',
    badgeClass: 'bg-success',
  },
  {
    title: 'Mapeo POS RestoTech',
    to: { path: '/pos/mapeo' },
    icon: { icon: 'ri-git-merge-line' },
  },
  {
    title: 'Control Caja Chica',
    to: { path: '/caja-chica' },
    icon: { icon: 'ri-wallet-3-line' },
  },
  {
    title: 'Taxis y Rotación Chicas',
    to: { path: '/movilidad/taxis' },
    icon: { icon: 'ri-taxi-line' },
  },
]

