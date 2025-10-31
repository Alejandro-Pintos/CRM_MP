export default [
  {
    title: 'Inicio',
    to: { name: 'root' },
    icon: { icon: 'ri-home-smile-2-line' },
  },
  {
    title: 'Panel de control',
    to: { name: 'dashboard' },
    icon: { icon: 'ri-dashboard-line' },
  },

  {
    title: 'Clientes',
    icon: { icon: 'ri-group-line' },
    children: [
      {
        title: 'Lista de Clientes',
        to: '/clientes',
        icon: { icon: 'ri-group-line' },
      },
      {
        title: 'Cuentas Corrientes',
        to: '/clientes/cuentas-corrientes',
        icon: { icon: 'ri-file-list-2-line' },
      },
    ],
  },
  {
    title: 'Proveedores',
    to: '/proveedores',
    icon: { icon: 'ri-contacts-line' },
  },
  {
    title: 'Productos',
    to: '/productos',
    icon: { icon: 'ri-tree-line' },
  },
  {
    title: 'Pedidos',
    to: '/pedidos',
    icon: { icon: 'ri-shopping-cart-line' },
  },
  {
    title: 'Ventas',
    icon: { icon: 'ri-funds-line' },
    children: [
      {
        title: 'Nueva Venta',
        to: '/ventas/nueva',
        icon: { icon: 'ri-add-circle-line' },
      },
      {
        title: 'Historial',
        to: '/ventas',
        icon: { icon: 'ri-file-list-3-line' },
      },
    ],
  },
  {
    title: 'Reportes',
    to: '/reportes',
    icon: { icon: 'ri-folder-chart-line' },
  },
]
