<template>
  <div class="h-100 d-flex align-center justify-space-between text-medium-emphasis flex-wrap">
    <!-- 👉 Footer: left content -->
    <div class="d-flex align-center text-base">
      &copy; {{ new Date().getFullYear() }} CRM-MP | Sistema de Gestión Empresarial
    </div>

    <!-- 👉 Footer: center - Desarrolladores -->
    <div class="d-flex align-center gap-x-6 text-sm">
      <div class="d-flex align-center gap-x-2">
        <VIcon icon="mdi-account-circle" size="18" />
        <span class="font-weight-medium">Desarrolladores:</span>
      </div>
      
      <!-- Desarrollador 1 -->
      <div class="d-flex align-center gap-x-1">
        <span>Alejandro Pintos</span>
        <a
          href="https://github.com/Alejandro-Pintos"
          target="_blank"
          rel="noopener noreferrer"
          class="text-primary"
          title="GitHub"
        >
          <VIcon icon="mdi-github" size="18" />
        </a>
        <a
          href="https://www.linkedin.com/in/alejandro-pintos"
          target="_blank"
          rel="noopener noreferrer"
          class="text-primary"
          title="LinkedIn"
        >
          <VIcon icon="mdi-linkedin" size="18" />
        </a>
      </div>

      <!-- Desarrollador 2 -->
      <div class="d-flex align-center gap-x-1">
        <span>Manuel Pereyra</span>
        <a
          href="https://github.com/manuel-pereyra"
          target="_blank"
          rel="noopener noreferrer"
          class="text-primary"
          title="GitHub"
        >
          <VIcon icon="mdi-github" size="18" />
        </a>
        <a
          href="https://www.linkedin.com/in/manuel-pereyra"
          target="_blank"
          rel="noopener noreferrer"
          class="text-primary"
          title="LinkedIn"
        >
          <VIcon icon="mdi-linkedin" size="18" />
        </a>
      </div>
    </div>

    <!-- 👉 Footer: right content -->
    <span class="d-md-flex gap-x-4 text-primary d-none">
      <a
        href="https://github.com/Alejandro-Pintos/CRM_MP/blob/main/LICENSE"
        target="_blank"
        rel="noopener noreferrer"
      >Licencia</a>
      <a
        href="https://github.com/Alejandro-Pintos/CRM_MP/blob/main/README.md"
        target="_blank"
        rel="noopener noreferrer"
      >Documentación</a>
      <a
        href="https://github.com/Alejandro-Pintos/CRM_MP/issues"
        target="_blank"
        rel="noopener noreferrer"
      >Soporte</a>
    </span>
  </div>
</template>
