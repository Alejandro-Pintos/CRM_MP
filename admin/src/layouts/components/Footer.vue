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
          <VIcon icon="ri-github-line" size="18" />
        </a>
        <a
          href="https://www.linkedin.com/in/alejandro-pintos-44b0a62a6/"
          target="_blank"
          rel="noopener noreferrer"
          class="text-primary"
          title="LinkedIn"
        >
          <VIcon icon="ri-linkedin-box-line" size="18" />
        </a>
      </div>

      <!-- Desarrollador 2 -->
      <div class="d-flex align-center gap-x-1">
        <span>Marcelo Hugo Coronel</span>
        <a
          href="https://github.com/marcelo-coronel"
          target="_blank"
          rel="noopener noreferrer"
          class="text-primary"
          title="GitHub"
        >
          <VIcon icon="ri-github-line" size="18" />
        </a>
        <a
          href="https://www.linkedin.com/in/marcelo-coronel"
          target="_blank"
          rel="noopener noreferrer"
          class="text-primary"
          title="LinkedIn"
        >
          <VIcon icon="ri-linkedin-box-line" size="18" />
        </a>
      </div>
    </div>

    <!-- 👉 Footer: right content -->
    <span class="d-md-flex gap-x-4 text-primary d-none">
      <a
        href="https://github.com/Alejandro-Pintos/CRM_MP/blob/main/LICENSE"
        target="_blank"
        rel="noopener noreferrer"
        class="d-flex align-center gap-x-1"
      >
        <VIcon icon="mdi-license" size="16" />
        <span>Licencia</span>
      </a>
      <a
        href="https://github.com/Alejandro-Pintos/CRM_MP/blob/main/README.md"
        target="_blank"
        rel="noopener noreferrer"
        class="d-flex align-center gap-x-1"
      >
        <VIcon icon="mdi-file-document" size="16" />
        <span>Documentación</span>
      </a>
      <a
        href="mailto:soporte.crmmp@gmail.com?subject=Soporte CRM-MP&body=Describe tu problema aquí..."
        class="d-flex align-center gap-x-1"
      >
        <VIcon icon="mdi-email" size="16" />
        <span>Soporte</span>
      </a>
    </span>
  </div>
</template>
