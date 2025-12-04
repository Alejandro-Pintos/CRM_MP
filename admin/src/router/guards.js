// Funciones para manejo de tokens y sesiones
function parseJwt(token) {
  try {
    const base64Url = token.split('.')[1];
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
      return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
    }).join(''));
    return JSON.parse(jsonPayload);
  } catch (e) {
    return null;
  }
}

function isTokenExpired(token) {
  const decodedToken = parseJwt(token);
  if (!decodedToken || !decodedToken.exp) {
    return true;
  }
  const currentTime = Math.floor(Date.now() / 1000);
  return decodedToken.exp < currentTime;
}

function updateLastActivity() {
  localStorage.setItem('lastActivity', Date.now().toString());
}

export const setupGuards = router => {
  // Configurar detección de actividad del usuario
  const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
  activityEvents.forEach(event => {
    document.addEventListener(event, updateLastActivity, { passive: true });
  });

  // Función para actualizar datos del usuario desde API
  const refreshUserData = async (token) => {
    try {
      console.log('[Guards] Refrescando datos del usuario...')
      const apiUrl = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000';
      const response = await fetch(`${apiUrl}/api/v1/me`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      });

      console.log('[Guards] Respuesta /me:', response.status, response.statusText)

      if (response.ok) {
        const contentType = response.headers.get('content-type')
        if (!contentType || !contentType.includes('application/json')) {
          console.error('[Guards] /me devolvió HTML en lugar de JSON:', contentType)
          return null
        }
        
        const result = await response.json();
        // Laravel Resources envuelven en {data: {...}}
        const userData = result.data || result
        console.log('[Guards] Usuario obtenido:', userData)
        localStorage.setItem('userData', JSON.stringify(userData));
        return userData;
      } else {
        console.warn('[Guards] /me falló con status:', response.status)
        return null;
      }
    } catch (error) {
      console.error('[Guards] Error al refrescar datos del usuario:', error);
      return null;
    }
  };

  router.beforeEach(async (to, from, next) => {
    console.log('[Guards] Navegando de', from.name, 'a', to.name)
    
    const publicPages = [
      'authentication-register-v1',
      'forgot-password',
      'login'
    ];
    const token = localStorage.getItem("accessToken");
    const userDataString = localStorage.getItem("userData");
    let user = userDataString ? JSON.parse(userDataString) : null;

    console.log('[Guards] Estado:', {
      hasToken: !!token,
      hasUser: !!user,
      toPage: to.name,
      isPublic: publicPages.includes(to.name)
    })

    // Si el usuario existe pero no tiene roles/permisos, actualizar desde API
    // IMPORTANTE: No intentar refrescar si vamos a una página pública (evita errores de sesión expirada en login)
    if (user && token && (!user.roles || !user.permissions) && !publicPages.includes(to.name)) {
      console.log('[Guards] Usuario sin roles/permisos, refrescando...')
      const updatedUser = await refreshUserData(token);
      if (updatedUser) {
        user = updatedUser;
      } else {
        console.warn('[Guards] No se pudo refrescar usuario, limpiando sesión')
        // Si no se puede actualizar, hacer logout
        localStorage.removeItem('accessToken');
        localStorage.removeItem('userData');
        return next({ name: 'login' });
      }
    }

    // Verificar si el token está expirado
    if (token && isTokenExpired(token)) {
      console.warn('[Guards] Token expirado, limpiando sesión')
      localStorage.removeItem("accessToken");
      localStorage.removeItem("userData");
      localStorage.removeItem("sessionStartTime");
      localStorage.removeItem("lastActivity");
      
      if (!publicPages.includes(to.name)) {
        return next({ name: 'login' });
      }
    }

    // Si no está autenticado y la ruta no es pública, redirige a login
    if (!token && !publicPages.includes(to.name)) {
      console.log('[Guards] Sin token y página privada, redirigiendo a login')
      if (to.name !== 'login') {
        return next({ name: 'login' });
      } else {
        return next();
      }
    }

    // Permitir acceso total al Super-Admin
    const isSuperAdmin = user && user.roles && user.roles.some(r => {
      // Los roles pueden venir como strings o como objetos {name: 'admin'}
      const roleName = typeof r === 'string' ? r : r.name;
      return roleName === 'superadmin' || roleName === 'Super-Admin' || roleName === 'super-admin' || roleName === 'admin';
    });
    if (isSuperAdmin) {
      return next();
    }

    // Si la ruta requiere rol y el usuario no lo tiene, redirige al home
    if (to.meta && to.meta.requiresRole) {
      const hasRole = user && user.roles && user.roles.some(r => {
        const roleName = typeof r === 'string' ? r : r.name;
        return roleName === to.meta.requiresRole;
      });
      if (!hasRole) {
        return next({ name: 'root' });
      }
    }

    // Si la ruta requiere permiso y el usuario no lo tiene, redirige al home
    if (to.meta && to.meta.permission) {
      const hasPermission = user && user.permissions && user.permissions.includes(to.meta.permission);
      if (!hasPermission) {
        return next({ name: 'root' });
      }
    }

    // Si está autenticado y va a login, redirige al home
    if (token && to.name === 'login') {
      return next({ name: 'root' });
    }

    // Si todo está bien, continúa
    next();
  });
}
