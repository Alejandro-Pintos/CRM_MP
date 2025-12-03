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

  // === OPERACIONES PRINCIPALES (Flujo de negocio) ===
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
        title: 'Presupuestador',
        to: '/ventas/presupuesto',
        icon: { icon: 'ri-file-text-line' },
      },
      {
        title: 'Historial',
        to: '/ventas',
        icon: { icon: 'ri-file-list-3-line' },
      },
    ],
  },
  {
    title: 'Pagos',
    icon: { icon: 'ri-money-dollar-circle-line' },
    children: [
      {
        title: 'Cheques',
        to: '/pagos/cheques',
        icon: { icon: 'ri-bank-card-line' },
      },
    ],
  },
  {
    title: 'Pedidos',
    to: '/pedidos',
    icon: { icon: 'ri-shopping-cart-line' },
  },

  // === GESTIÓN DE RELACIONES ===
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

  // === CATÁLOGO Y RECURSOS ===
  {
    title: 'Productos',
    to: '/productos',
    icon: { icon: 'ri-tree-line' },
  },
  {
    title: 'Proveedores',
    to: '/proveedores',
    icon: { icon: 'ri-contacts-line' },
  },
  {
    title: 'Empleados',
    to: '/empleados',
    icon: { icon: 'ri-team-line' },
  },

  // === ANÁLISIS Y REPORTES ===
  {
    title: 'Reportes',
    to: '/reportes',
    icon: { icon: 'ri-folder-chart-line' },
  },
]
