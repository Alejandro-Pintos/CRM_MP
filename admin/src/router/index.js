import { setupLayouts } from 'virtual:generated-layouts'
import { createRouter, createWebHistory } from 'vue-router/auto'
import { setupGuards } from './guards';

function recursiveLayouts(route) {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])
    
    return route
  }
  
  return setupLayouts([route])[0]
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }
    
    return { top: 0 }
  },
  extendRoutes: pages => [
    ...[
      {
        path: '/',
        name: 'root',
        redirect: to => {
          // ✅ Cambiar "usuario" por "accessToken" o "userData"
          const accessToken = localStorage.getItem("accessToken");
          const userData = localStorage.getItem("userData");
          
          if (accessToken && userData) {
            // Buscar la primera página que no sea login
            const dashboardPage = pages.find(page => 
              page.name === 'dashboard' || 
              page.name === 'home' || 
              page.name === 'index' ||
              page.path === '/dashboard'
            );
            if (dashboardPage) {
              return { name: dashboardPage.name };
            }
            // Si no encuentra dashboard, ir a la primera página disponible que no sea login
            const firstPage = pages.find(page => page.name !== 'login');
            return firstPage ? { name: firstPage.name } : { name: 'login' };
          }
          return { name: 'login', query: to.query }
        },
      },
    ],
    ...pages.map(route => recursiveLayouts(route)),
  ],
})
setupGuards(router)
export { router }
export default function (app) {
  app.use(router)
}
