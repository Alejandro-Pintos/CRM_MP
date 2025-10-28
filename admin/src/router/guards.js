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

/* function isUserInactive() {
  const lastActivity = localStorage.getItem('lastActivity');
  const sessionStartTime = localStorage.getItem('sessionStartTime');
  
  if (!lastActivity || !sessionStartTime) {
    return true;
  }
  
  const now = Date.now();
  const lastActivityTime = parseInt(lastActivity);
  const maxInactiveTime = 25 * 60 * 1000; // 25 minutos en milisegundos
  
  return (now - lastActivityTime) > maxInactiveTime;
} */

function updateLastActivity() {
  localStorage.setItem('lastActivity', Date.now().toString());
}

/* function checkInactivityAndLogout() {
  if (isUserInactive()) {
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    localStorage.removeItem("sessionStartTime");
    localStorage.removeItem("lastActivity");
    
    if (window.location.pathname !== '/login') {
      window.location.href = '/login';
    }
  }
} */

export const setupGuards = router => {
  // Configurar detección de actividad del usuario
  const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
  activityEvents.forEach(event => {
    document.addEventListener(event, updateLastActivity, { passive: true });
  });

  // Verificar inactividad cada 30 segundos
  //setInterval(checkInactivityAndLogout, 30000);

  // Función para actualizar datos del usuario desde API
  const refreshUserData = async (token) => {
    try {
      const apiUrl = import.meta.env.VITE_API_BASE_URL;
      const response = await fetch(`${apiUrl}/auth/me`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      });
      
      if (response.ok) {
        const userData = await response.json();
        localStorage.setItem('user', JSON.stringify(userData));
        return userData;
      } else {
        return null;
      }
    } catch (error) {
      return null;
    }
  };

  router.beforeEach(async (to, from, next) => {
    const publicPages = [
      'authentication-register-v1',
      'forgot-password',
      'login'
    ];
    const token = localStorage.getItem("token");
    const userData = localStorage.getItem("user");
    let user = userData ? JSON.parse(userData) : null;

    // Si el usuario existe pero no tiene roles/permisos, actualizar desde API
    if (user && token && (!user.roles || !user.permissions)) {
      const updatedUser = await refreshUserData(token);
      if (updatedUser) {
        user = updatedUser;
      } else {
        // Si no se puede actualizar, hacer logout
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        return next({ name: 'login' });
      }
    }

    // Si el token está expirado o el usuario está inactivo, limpiar localStorage
    // (Descomenta si quieres usar la expiración/inactividad)
    // if (token && (isTokenExpired(token) || isUserInactive())) {
    //   localStorage.removeItem("token");
    //   localStorage.removeItem("user");
    //   localStorage.removeItem("sessionStartTime");
    //   localStorage.removeItem("lastActivity");
    // }

    // Si no está autenticado y la ruta no es pública, redirige a login
    if (!user && !publicPages.includes(to.name)) {
      if (to.name !== 'login') {
        return next({ name: 'login' });
      } else {
        return next();
      }
    }

    // Permitir acceso total al Super-Admin
    const isSuperAdmin = user && user.roles && user.roles.some(r => 
      r.name === 'superadmin' || r.name === 'Super-Admin' || r.name === 'super-admin'
    );
    if (isSuperAdmin) {
      return next();
    }

    // Si la ruta requiere rol y el usuario no lo tiene, redirige al home
    if (to.meta && to.meta.requiresRole) {
      const hasRole = user && user.roles && user.roles.some(r => r.name === to.meta.requiresRole);
      if (!hasRole) {
        return next({ name: 'root' }); // si tu home es 'root'
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
    if (user && to.name === 'login') {
      return next({ name: 'root' });
    }

    // Si todo está bien, continúa
    next();
  });
}
