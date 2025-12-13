<script setup>
import { ref } from 'vue'

const activeSection = ref('introduccion')

const sections = [
  { id: 'introduccion', title: '1. Introducción', icon: 'mdi-information' },
  { id: 'requisitos', title: '2. Requisitos y Acceso', icon: 'mdi-key' },
  { id: 'estructura', title: '3. Estructura General', icon: 'mdi-view-dashboard' },
  { id: 'clientes', title: '4. Módulo Clientes', icon: 'mdi-account-group' },
  { id: 'productos', title: '5. Módulo Productos', icon: 'mdi-package-variant' },
  { id: 'proveedores', title: '6. Módulo Proveedores', icon: 'mdi-truck-delivery' },
  { id: 'ventas', title: '7. Módulo Ventas', icon: 'mdi-cart' },
  { id: 'pagos', title: '8. Módulo Pagos', icon: 'mdi-cash-multiple' },
  { id: 'pedidos', title: '9. Módulo Pedidos', icon: 'mdi-clipboard-list' },
  { id: 'empleados', title: '10. Módulo Empleados', icon: 'mdi-account-tie' },
  { id: 'reportes', title: '11. Módulo Reportes', icon: 'mdi-chart-bar' },
  { id: 'perfil', title: '12. Perfil de Usuario', icon: 'mdi-account-circle' },
  { id: 'buenas-practicas', title: '13. Buenas Prácticas', icon: 'mdi-star' },
  { id: 'faq', title: '14. Preguntas Frecuentes', icon: 'mdi-help-circle' }
]

const scrollToSection = (sectionId) => {
  activeSection.value = sectionId
  const element = document.getElementById(sectionId)
  if (element) {
    element.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}
</script>

<template>
  <div>
    <!-- Encabezado -->
    <VCard class="mb-6">
      <VCardText class="text-center py-6">
        <VIcon
          icon="mdi-book-open-page-variant"
          size="64"
          color="primary"
          class="mb-3"
        />
        <h1 class="text-h3 font-weight-bold mb-2">
          Manual de Usuario
        </h1>
        <h2 class="text-h5 text-medium-emphasis">
          E.R.P MADERAS PANI
        </h2>
        <p class="text-body-1 mt-3">
          Guía completa para aprovechar al máximo todas las funcionalidades del sistema
        </p>
      </VCardText>
    </VCard>

    <VRow>
      <!-- Índice de Navegación (Columna Izquierda) -->
      <VCol
        cols="12"
        md="3"
      >
        <VCard class="sticky-top">
          <VCardTitle>Índice</VCardTitle>
          <VDivider />
          <VList density="compact">
            <VListItem
              v-for="section in sections"
              :key="section.id"
              :active="activeSection === section.id"
              @click="scrollToSection(section.id)"
              class="cursor-pointer"
            >
              <template #prepend>
                <VIcon :icon="section.icon" size="20" class="mr-2" />
              </template>
              <VListItemTitle>{{ section.title }}</VListItemTitle>
            </VListItem>
          </VList>
        </VCard>
      </VCol>

      <!-- Contenido del Manual (Columna Derecha) -->
      <VCol
        cols="12"
        md="9"
      >
        <!-- 1. Introducción -->
        <VCard
          id="introduccion"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-information" size="28" class="mr-3" color="primary" />
            1. Introducción
          </VCardTitle>
          <VDivider />
          <VCardText>
            <p class="text-body-1 mb-3">
              Bienvenido al <strong>E.R.P MADERAS PANI</strong>, un sistema de gestión empresarial diseñado específicamente para optimizar y centralizar todas las operaciones de la empresa Maderas Pani.
            </p>
            <p class="text-body-1 mb-3">
              Este sistema le permite:
            </p>
            <ul class="mb-3">
              <li>Gestionar el catálogo completo de productos y stock</li>
              <li>Administrar clientes y proveedores con cuentas corrientes</li>
              <li>Registrar y controlar ventas, pagos y pedidos</li>
              <li>Emitir y gestionar cheques de proveedores</li>
              <li>Generar reportes detallados con filtros personalizados</li>
              <li>Controlar usuarios y permisos de acceso</li>
            </ul>
            <p class="text-body-1">
              El objetivo de este manual es guiarlo paso a paso en el uso de cada módulo del sistema, asegurando que pueda aprovechar todas sus funcionalidades de manera eficiente.
            </p>
          </VCardText>
        </VCard>

        <!-- 2. Requisitos y Acceso -->
        <VCard
          id="requisitos"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-key" size="28" class="mr-3" color="primary" />
            2. Requisitos y Acceso
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              Requisitos del Sistema
            </h3>
            <ul class="mb-4">
              <li>Navegador web moderno (Google Chrome, Firefox, Edge o Safari)</li>
              <li>Conexión a Internet estable</li>
              <li>Credenciales de acceso proporcionadas por el administrador</li>
            </ul>

            <h3 class="text-h6 font-weight-bold mb-3">
              Cómo Ingresar al Sistema
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                Abra su navegador web e ingrese la URL del sistema (proporcionada por su administrador)
              </li>
              <li class="mb-2">
                En la pantalla de inicio de sesión, ingrese su <strong>correo electrónico</strong> y <strong>contraseña</strong>
              </li>
              <li class="mb-2">
                Haga clic en el botón <strong>"Iniciar Sesión"</strong>
              </li>
              <li class="mb-2">
                Si las credenciales son correctas, será redirigido al panel principal del sistema
              </li>
            </ol>

            <h3 class="text-h6 font-weight-bold mb-3">
              Recuperación de Contraseña
            </h3>
            <p class="text-body-1 mb-2">
              Si olvidó su contraseña:
            </p>
            <ol class="mb-3">
              <li class="mb-2">
                En la pantalla de inicio de sesión, haga clic en <strong>"¿Olvidó su contraseña?"</strong>
              </li>
              <li class="mb-2">
                Ingrese su correo electrónico registrado
              </li>
              <li class="mb-2">
                Recibirá un correo con instrucciones para restablecer su contraseña
              </li>
              <li class="mb-2">
                Siga el enlace del correo y cree una nueva contraseña segura
              </li>
            </ol>
          </VCardText>
        </VCard>

        <!-- 3. Estructura General del Sistema -->
        <VCard
          id="estructura"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-view-dashboard" size="28" class="mr-3" color="primary" />
            3. Estructura General del Sistema
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              Navegación Principal
            </h3>
            <p class="text-body-1 mb-3">
              El sistema cuenta con una interfaz intuitiva dividida en las siguientes áreas:
            </p>

            <h4 class="text-subtitle-1 font-weight-bold mb-2">
              Menú Lateral (Sidebar)
            </h4>
            <p class="text-body-1 mb-3">
              Ubicado en el lado izquierdo de la pantalla, contiene todos los módulos del sistema:
            </p>
            <ul class="mb-4">
              <li><strong>Inicio:</strong> Panel de bienvenida con acceso rápido a módulos</li>
              <li><strong>Ventas:</strong> Gestión de ventas realizadas</li>
              <li><strong>Pagos:</strong> Control de cobros y pagos</li>
              <li><strong>Pedidos:</strong> Administración de pedidos</li>
              <li><strong>Clientes:</strong> Gestión de clientes y cuentas corrientes</li>
              <li><strong>Productos:</strong> Catálogo de productos y stock</li>
              <li><strong>Proveedores:</strong> Gestión de proveedores y cheques</li>
              <li><strong>Empleados:</strong> Administración de usuarios del sistema</li>
              <li><strong>Reportes:</strong> Generación de reportes e informes</li>
            </ul>

            <h4 class="text-subtitle-1 font-weight-bold mb-2">
              Barra Superior (Header)
            </h4>
            <p class="text-body-1 mb-3">
              En la parte superior derecha encontrará:
            </p>
            <ul class="mb-4">
              <li><strong>Icono de tema:</strong> Cambiar entre modo claro y oscuro</li>
              <li><strong>Avatar de usuario:</strong> Acceso a su perfil y opción de cerrar sesión</li>
            </ul>

            <h4 class="text-subtitle-1 font-weight-bold mb-2">
              Área de Contenido
            </h4>
            <p class="text-body-1 mb-3">
              Es el espacio principal donde se muestran los datos y formularios de cada módulo.
            </p>

            <VAlert
              type="info"
              variant="tonal"
              class="mt-4"
            >
              <strong>Consejo:</strong> Puede colapsar el menú lateral haciendo clic en el icono de pin para tener más espacio de trabajo.
            </VAlert>
          </VCardText>
        </VCard>

        <!-- 4. Módulo Clientes -->
        <VCard
          id="clientes"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-account-group" size="28" class="mr-3" color="primary" />
            4. Módulo Clientes
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              Descripción
            </h3>
            <p class="text-body-1 mb-4">
              Este módulo permite gestionar toda la información de los clientes de Maderas Pani, incluyendo datos personales, límites de crédito, cuentas corrientes y estados de cuenta.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              Cómo Crear un Nuevo Cliente
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                En el menú lateral, haga clic en <strong>"Clientes"</strong>
              </li>
              <li class="mb-2">
                Haga clic en el botón <strong>"Nuevo Cliente"</strong> (esquina superior derecha)
              </li>
              <li class="mb-2">
                Complete el formulario con los datos del cliente:
                <ul class="mt-2">
                  <li>Nombre y Apellido (obligatorios)</li>
                  <li>Email y Teléfono</li>
                  <li>Dirección, Ciudad y Provincia</li>
                  <li>CUIT/CUIL</li>
                  <li>Límite de crédito</li>
                  <li>Requiere factura (Sí/No)</li>
                </ul>
              </li>
              <li class="mb-2">
                Haga clic en <strong>"Guardar"</strong>
              </li>
            </ol>

            <h3 class="text-h6 font-weight-bold mb-3">
              Consultar Estado de Cuenta de un Cliente
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                En la lista de clientes, localice al cliente deseado
              </li>
              <li class="mb-2">
                Haga clic en el icono del ojo (👁️) en la columna "Acciones"
              </li>
              <li class="mb-2">
                Visualice el estado de cuenta con:
                <ul class="mt-2">
                  <li>Saldo actual</li>
                  <li>Límite de crédito</li>
                  <li>Crédito disponible</li>
                  <li>Historial de movimientos (ventas y pagos)</li>
                </ul>
              </li>
            </ol>

            <VAlert
              type="warning"
              variant="tonal"
              class="mt-4"
            >
              <strong>Importante:</strong> No puede eliminar un cliente que tenga ventas o movimientos de cuenta corriente registrados.
            </VAlert>
          </VCardText>
        </VCard>

        <!-- 5. Módulo Productos -->
        <VCard
          id="productos"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-package-variant" size="28" class="mr-3" color="primary" />
            5. Módulo Productos
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              Descripción
            </h3>
            <p class="text-body-1 mb-4">
              Administre el catálogo completo de productos, incluyendo precios, stock disponible, categorías y proveedores.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              Cómo Crear un Nuevo Producto
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                En el menú lateral, haga clic en <strong>"Productos"</strong>
              </li>
              <li class="mb-2">
                Haga clic en el botón <strong>"Nuevo Producto"</strong>
              </li>
              <li class="mb-2">
                Complete los datos:
                <ul class="mt-2">
                  <li>Nombre del producto</li>
                  <li>Código / SKU</li>
                  <li>Categoría</li>
                  <li>Precio de venta</li>
                  <li>Stock disponible</li>
                  <li>Stock mínimo (para alertas)</li>
                  <li>Proveedor principal</li>
                  <li>Descripción</li>
                </ul>
              </li>
              <li class="mb-2">
                Haga clic en <strong>"Guardar"</strong>
              </li>
            </ol>

            <h3 class="text-h6 font-weight-bold mb-3">
              Búsqueda y Filtrado
            </h3>
            <p class="text-body-1 mb-2">
              Puede buscar productos utilizando:
            </p>
            <ul class="mb-4">
              <li>Campo de búsqueda por nombre o código</li>
              <li>Filtro por categoría</li>
              <li>Filtro por proveedor</li>
              <li>Filtro de productos con stock bajo</li>
            </ul>

            <VAlert
              type="info"
              variant="tonal"
              class="mt-4"
            >
              <strong>Consejo:</strong> Configure alertas de stock mínimo para recibir notificaciones cuando un producto esté por agotarse.
            </VAlert>
          </VCardText>
        </VCard>

        <!-- 6. Módulo Proveedores -->
        <VCard
          id="proveedores"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-truck-delivery" size="28" class="mr-3" color="primary" />
            6. Módulo Proveedores
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              <VIcon icon="mdi-information-outline" size="20" class="mr-2" />
              Descripción
            </h3>
            <p class="text-body-1 mb-4">
              Gestione proveedores, cuentas corrientes, compras, pagos, cheques emitidos y estados de cuenta. El módulo incluye tres pestañas principales: Listado de Proveedores, Compras a Proveedores, y Pagos a Proveedores.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              Cómo Registrar un Nuevo Proveedor
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                Navegue a <strong>"Proveedores"</strong> en el menú lateral
              </li>
              <li class="mb-2">
                Haga clic en <strong>"Nuevo Proveedor"</strong>
              </li>
              <li class="mb-2">
                Complete los datos:
                <ul class="mt-2">
                  <li>Nombre / Razón Social</li>
                  <li>CUIT</li>
                  <li>Email y Teléfono</li>
                  <li>Dirección</li>
                  <li>Condiciones de pago</li>
                </ul>
              </li>
              <li class="mb-2">
                Guarde el proveedor
              </li>
            </ol>

            <h3 class="text-h6 font-weight-bold mb-3">
              <VIcon icon="mdi-cart-plus" size="20" class="mr-2" />
              Registrar Compra a Proveedor
            </h3>
            <ol class="mb-4">
              <li class="mb-2">Vaya a <strong>"Proveedores"</strong> → <strong>"Compras a Proveedores"</strong></li>
              <li class="mb-2">Seleccione el proveedor y haga clic en <strong>"Registrar Compra"</strong></li>
              <li class="mb-2">Complete la fecha de compra y agregue los items:
                <ul class="mt-2">
                  <li>Descripción del producto/material</li>
                  <li>Cantidad</li>
                  <li>Precio unitario</li>
                  <li>IVA (opcional)</li>
                </ul>
              </li>
              <li class="mb-2">El sistema no permite agregar items vacíos (validación automática)</li>
              <li class="mb-2">Revise el total y confirme la compra</li>
            </ol>

            <h3 class="text-h6 font-weight-bold mb-3">
              <VIcon icon="mdi-cash-multiple" size="20" class="mr-2" />
              Registrar Pago a Proveedor
            </h3>
            <ol class="mb-4">
              <li class="mb-2">Vaya a <strong>"Proveedores"</strong> → <strong>"Pagos a Proveedores"</strong></li>
              <li class="mb-2">El sistema muestra solo proveedores con saldo pendiente</li>
              <li class="mb-2">Seleccione el proveedor y haga clic en <strong>"Registrar Pago"</strong></li>
              <li class="mb-2">Ingrese el monto a pagar</li>
              <li class="mb-2">Seleccione el método de pago (Efectivo, Transferencia, Cheque)</li>
              <li class="mb-2">Si paga con cheque, complete los datos del cheque</li>
            </ol>

            <h3 class="text-h6 font-weight-bold mb-3">
              <VIcon icon="mdi-checkbook" size="20" class="mr-2" />
              Gestión de Cheques Emitidos
            </h3>
            <p class="text-body-1 mb-2">
              Para registrar un cheque emitido a un proveedor:
            </p>
            <ol class="mb-4">
              <li class="mb-2">
                Vaya a <strong>"Proveedores"</strong> → <strong>"Cheques Emitidos"</strong>
              </li>
              <li class="mb-2">
                Visualice todos los cheques emitidos con filtros por estado
              </li>
              <li class="mb-2">
                Cheques creados desde "Pagos a Proveedores" aparecen aquí automáticamente
              </li>
              <li class="mb-2">
                Cambie el estado del cheque según corresponda:
                <ul class="mt-2">
                  <li><strong>Pendiente:</strong> Cheque emitido, no cobrado</li>
                  <li><strong>Cobrado:</strong> Proveedor cobró el cheque</li>
                  <li><strong>Rechazado:</strong> Cheque rechazado por el banco</li>
                </ul>
              </li>
            </ol>
          </VCardText>
        </VCard>

        <!-- 7. Módulo Ventas -->
        <VCard
          id="ventas"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-cart" size="28" class="mr-3" color="primary" />
            7. Módulo Ventas
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              <VIcon icon="mdi-information-outline" size="20" class="mr-2" />
              Descripción
            </h3>
            <p class="text-body-1 mb-4">
              Registre y gestione todas las ventas realizadas, con diferentes formas de pago y seguimiento de cuenta corriente. Incluye indicador visual de subtotal reactivo para facilitar el proceso de venta.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              <VIcon icon="mdi-cash-register" size="20" class="mr-2" />
              Cómo Registrar una Venta
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                Vaya a <strong>"Ventas"</strong> en el menú
              </li>
              <li class="mb-2">
                Haga clic en <strong>"Nueva Venta"</strong>
              </li>
              <li class="mb-2">
                Seleccione el <strong>cliente</strong>
              </li>
              <li class="mb-2">
                Agregue productos:
                <ul class="mt-2">
                  <li>Busque el producto por nombre o código</li>
                  <li>Ingrese la cantidad</li>
                  <li>🆕 <strong>Nuevo:</strong> Verá un indicador visual del subtotal (cantidad × precio) que se actualiza en tiempo real</li>
                  <li>Haga clic en "Agregar" para añadir el producto al carrito</li>
                </ul>
              </li>
              <li class="mb-2">
                Seleccione la <strong>forma de pago</strong>:
                <ul class="mt-2">
                  <li><strong>Efectivo:</strong> Pago inmediato en efectivo</li>
                  <li><strong>Transferencia:</strong> Pago por transferencia bancaria</li>
                  <li><strong>Cuenta Corriente:</strong> Se registra la deuda en la CC del cliente</li>
                </ul>
              </li>
              <li class="mb-2">
                Verifique el total y confirme la venta
              </li>
            </ol>

            <VAlert
              type="warning"
              variant="tonal"
              class="mt-4"
            >
              <strong>Importante:</strong> Al vender con Cuenta Corriente, verifique que el cliente tenga crédito disponible suficiente.
            </VAlert>
          </VCardText>
        </VCard>

        <!-- 8. Módulo Pagos -->
        <VCard
          id="pagos"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-cash-multiple" size="28" class="mr-3" color="primary" />
            8. Módulo Pagos
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              Descripción
            </h3>
            <p class="text-body-1 mb-4">
              Gestione todos los cobros de clientes y pagos a proveedores, incluyendo cheques.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              Cómo Registrar un Pago de Cliente
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                Navegue a <strong>"Pagos"</strong>
              </li>
              <li class="mb-2">
                Haga clic en <strong>"Nuevo Pago"</strong>
              </li>
              <li class="mb-2">
                Seleccione el <strong>cliente</strong> que realiza el pago
              </li>
              <li class="mb-2">
                Ingrese el <strong>monto</strong> abonado
              </li>
              <li class="mb-2">
                Seleccione la <strong>forma de pago</strong>:
                <ul class="mt-2">
                  <li>Efectivo</li>
                  <li>Transferencia</li>
                  <li>Cheque (complete datos del cheque)</li>
                </ul>
              </li>
              <li class="mb-2">
                Confirme el pago
              </li>
            </ol>

            <h3 class="text-h6 font-weight-bold mb-3">
              Registro de Cheques Recibidos
            </h3>
            <p class="text-body-1 mb-2">
              Cuando un cliente paga con cheque:
            </p>
            <ol class="mb-4">
              <li class="mb-2">
                Complete los datos del cheque:
                <ul class="mt-2">
                  <li>Número de cheque</li>
                  <li>Banco emisor</li>
                  <li>Fecha de emisión</li>
                  <li>Fecha de vencimiento</li>
                  <li>Monto</li>
                </ul>
              </li>
              <li class="mb-2">
                El cheque quedará registrado con estado "Pendiente"
              </li>
              <li class="mb-2">
                Al cobrarlo, actualice el estado a "Cobrado"
              </li>
            </ol>
          </VCardText>
        </VCard>

        <!-- 9. Módulo Pedidos -->
        <VCard
          id="pedidos"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-clipboard-list" size="28" class="mr-3" color="primary" />
            9. Módulo Pedidos
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              Descripción
            </h3>
            <p class="text-body-1 mb-4">
              Administre pedidos de clientes, control de entregas y seguimiento de estados.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              Cómo Crear un Pedido
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                Vaya a <strong>"Pedidos"</strong>
              </li>
              <li class="mb-2">
                Haga clic en <strong>"Nuevo Pedido"</strong>
              </li>
              <li class="mb-2">
                Seleccione el <strong>cliente</strong>
              </li>
              <li class="mb-2">
                Agregue los productos solicitados con sus cantidades
              </li>
              <li class="mb-2">
                Ingrese la <strong>fecha de entrega estimada</strong>
              </li>
              <li class="mb-2">
                El pedido se creará con estado <strong>"Pendiente"</strong>
              </li>
            </ol>

            <h3 class="text-h6 font-weight-bold mb-3">
              Estados de Pedidos
            </h3>
            <ul class="mb-4">
              <li><strong>Pendiente:</strong> Pedido registrado, en preparación</li>
              <li><strong>En proceso:</strong> Se está preparando el pedido</li>
              <li><strong>Listo:</strong> Pedido preparado, listo para entregar</li>
              <li><strong>Entregado:</strong> Pedido entregado al cliente</li>
              <li><strong>Cancelado:</strong> Pedido cancelado</li>
            </ul>

            <VAlert
              type="info"
              variant="tonal"
              class="mt-4"
            >
              <strong>Consejo:</strong> Actualice el estado de los pedidos regularmente para mantener un seguimiento preciso.
            </VAlert>
          </VCardText>
        </VCard>

        <!-- 10. Módulo Empleados / Usuarios -->
        <VCard
          id="empleados"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-account-tie" size="28" class="mr-3" color="primary" />
            10. Módulo Empleados / Usuarios
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              Descripción
            </h3>
            <p class="text-body-1 mb-4">
              Administre usuarios del sistema, roles y permisos de acceso. Este módulo está disponible solo para administradores.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              Cómo Crear un Usuario
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                Navegue a <strong>"Empleados"</strong>
              </li>
              <li class="mb-2">
                Haga clic en <strong>"Nuevo Usuario"</strong>
              </li>
              <li class="mb-2">
                Complete los datos:
                <ul class="mt-2">
                  <li>Nombre completo</li>
                  <li>Email (será el usuario de acceso)</li>
                  <li>Contraseña inicial</li>
                  <li>Rol (Admin, Vendedor, Contador, etc.)</li>
                </ul>
              </li>
              <li class="mb-2">
                Asigne los permisos específicos según el rol
              </li>
            </ol>

            <h3 class="text-h6 font-weight-bold mb-3">
              Roles y Permisos
            </h3>
            <ul class="mb-4">
              <li><strong>Administrador:</strong> Acceso completo a todos los módulos</li>
              <li><strong>Vendedor:</strong> Acceso a ventas, clientes, productos y pedidos</li>
              <li><strong>Contador:</strong> Acceso a reportes, cuentas corrientes y pagos</li>
              <li><strong>Almacén:</strong> Acceso a productos, stock y pedidos</li>
            </ul>

            <VAlert
              type="warning"
              variant="tonal"
              class="mt-4"
            >
              <strong>Importante:</strong> Revise cuidadosamente los permisos asignados a cada usuario para mantener la seguridad del sistema.
            </VAlert>
          </VCardText>
        </VCard>

        <!-- 11. Módulo Reportes -->
        <VCard
          id="reportes"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-chart-bar" size="28" class="mr-3" color="primary" />
            11. Módulo Reportes
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              <VIcon icon="mdi-information-outline" size="20" class="mr-2" />
              Descripción
            </h3>
            <p class="text-body-1 mb-4">
              Genere reportes detallados de ventas, productos, clientes y proveedores con filtros personalizados. Todos los reportes incluyen gráficos interactivos y exportación a Excel/CSV.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              <VIcon icon="mdi-file-chart" size="20" class="mr-2" />
              Tipos de Reportes Disponibles
            </h3>
            <ul class="mb-4">
              <li><strong>Reporte de Ventas:</strong> Ventas por período con métricas (ticket promedio, clientes únicos, productos vendidos, estado de pago)</li>
              <li><strong>Reporte de Clientes:</strong> Top clientes por ingresos, cuentas corrientes, estados de cuenta con 11 columnas de información</li>
              <li><strong>Reporte de Productos:</strong> Productos más vendidos, ingresos por producto, información de proveedores con 9 columnas</li>
              <li><strong>Reporte de Proveedores:</strong> Compras, pagos, saldo, participación en ventas, productos asociados con 14 columnas</li>
            </ul>

            <VAlert type="info" variant="tonal" class="mb-4">
              <strong>🆕 Nuevo:</strong> El reporte de proveedores ahora incluye la columna "Part. Ventas %" que muestra el porcentaje de participación de cada proveedor en las ventas totales basado en sus productos.
            </VAlert>

            <h3 class="text-h6 font-weight-bold mb-3">
              Cómo Generar un Reporte
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                Vaya a <strong>"Reportes"</strong>
              </li>
              <li class="mb-2">
                Seleccione el <strong>tipo de reporte</strong> deseado
              </li>
              <li class="mb-2">
                Configure los filtros:
                <ul class="mt-2">
                  <li>Rango de fechas (desde - hasta)</li>
                  <li>Cliente / Proveedor específico (opcional)</li>
                  <li>Producto o categoría (opcional)</li>
                  <li>Estado (opcional)</li>
                </ul>
              </li>
              <li class="mb-2">
                Haga clic en <strong>"Generar Reporte"</strong>
              </li>
              <li class="mb-2">
                Visualice los resultados en pantalla
              </li>
              <li class="mb-2">
                Exporte a <strong>Excel</strong> o <strong>PDF</strong> si lo necesita
              </li>
            </ol>

            <VAlert
              type="info"
              variant="tonal"
              class="mt-4"
            >
              <strong>Consejo:</strong> Genere reportes mensuales de ventas y cuentas corrientes para mantener un control financiero óptimo.
            </VAlert>
          </VCardText>
        </VCard>

        <!-- 12. Perfil de Usuario -->
        <VCard
          id="perfil"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-account-circle" size="28" class="mr-3" color="primary" />
            12. Perfil de Usuario y Configuración Básica
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              Acceder a su Perfil
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                Haga clic en su <strong>avatar</strong> (esquina superior derecha)
              </li>
              <li class="mb-2">
                Seleccione <strong>"Perfil"</strong> en el menú desplegable
              </li>
            </ol>

            <h3 class="text-h6 font-weight-bold mb-3">
              Información que Puede Modificar
            </h3>
            <ul class="mb-4">
              <li><strong>Nombre y Apellido:</strong> Actualice sus datos personales</li>
              <li><strong>Email:</strong> Cambie su correo de contacto</li>
              <li><strong>Teléfono:</strong> Actualice su número de contacto</li>
              <li><strong>Avatar:</strong> Cargue una foto de perfil personalizada</li>
              <li><strong>Contraseña:</strong> Cambie su contraseña de acceso</li>
            </ul>

            <h3 class="text-h6 font-weight-bold mb-3">
              Cómo Cambiar su Contraseña
            </h3>
            <ol class="mb-4">
              <li class="mb-2">
                Vaya a su <strong>Perfil</strong>
              </li>
              <li class="mb-2">
                Haga clic en <strong>"Cambiar Contraseña"</strong>
              </li>
              <li class="mb-2">
                Ingrese su <strong>contraseña actual</strong>
              </li>
              <li class="mb-2">
                Ingrese la <strong>nueva contraseña</strong> (dos veces para confirmar)
              </li>
              <li class="mb-2">
                Haga clic en <strong>"Actualizar Contraseña"</strong>
              </li>
            </ol>

            <VAlert
              type="warning"
              variant="tonal"
              class="mt-4"
            >
              <strong>Importante:</strong> Use contraseñas seguras con al menos 8 caracteres, incluyendo mayúsculas, minúsculas y números.
            </VAlert>
          </VCardText>
        </VCard>

        <!-- 13. Buenas Prácticas de Uso -->
        <VCard
          id="buenas-practicas"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-star" size="28" class="mr-3" color="primary" />
            13. Buenas Prácticas de Uso
          </VCardTitle>
          <VDivider />
          <VCardText>
            <p class="text-body-1 mb-3">
              Para aprovechar al máximo el sistema y mantener la integridad de los datos, siga estas recomendaciones:
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              Gestión de Datos
            </h3>
            <ul class="mb-4">
              <li class="mb-2">
                <strong>Actualice información regularmente:</strong> Mantenga los datos de clientes, proveedores y productos al día
              </li>
              <li class="mb-2">
                <strong>Verifique antes de guardar:</strong> Revise los datos ingresados antes de confirmar operaciones
              </li>
              <li class="mb-2">
                <strong>Use descripciones claras:</strong> Agregue observaciones en ventas, pagos y pedidos cuando sea necesario
              </li>
              <li class="mb-2">
                <strong>No elimine registros con historial:</strong> El sistema protege datos con movimientos asociados
              </li>
            </ul>

            <h3 class="text-h6 font-weight-bold mb-3">
              Seguridad
            </h3>
            <ul class="mb-4">
              <li class="mb-2">
                <strong>Cierre sesión al terminar:</strong> Especialmente en computadoras compartidas
              </li>
              <li class="mb-2">
                <strong>No comparta su contraseña:</strong> Cada usuario debe tener credenciales propias
              </li>
              <li class="mb-2">
                <strong>Cambie su contraseña periódicamente:</strong> Al menos cada 3 meses
              </li>
              <li class="mb-2">
                <strong>Reporte actividad sospechosa:</strong> Notifique al administrador si detecta algo inusual
              </li>
            </ul>

            <h3 class="text-h6 font-weight-bold mb-3">
              Operaciones Diarias
            </h3>
            <ul class="mb-4">
              <li class="mb-2">
                <strong>Registre operaciones el mismo día:</strong> No postergue el registro de ventas o pagos
              </li>
              <li class="mb-2">
                <strong>Revise reportes semanales:</strong> Mantenga control sobre ventas y cuentas corrientes
              </li>
              <li class="mb-2">
                <strong>Actualice estados de pedidos:</strong> Informe cambios de estado apenas ocurran
              </li>
              <li class="mb-2">
                <strong>Verifique stock regularmente:</strong> Controle productos con stock bajo
              </li>
            </ul>

            <h3 class="text-h6 font-weight-bold mb-3">
              Respaldos y Exportación
            </h3>
            <ul class="mb-4">
              <li class="mb-2">
                <strong>Exporte reportes importantes:</strong> Guarde copias en Excel o PDF de reportes clave
              </li>
              <li class="mb-2">
                <strong>Revise datos exportados:</strong> Verifique que la información exportada sea correcta
              </li>
            </ul>
          </VCardText>
        </VCard>

        <!-- 14. Preguntas Frecuentes (FAQ) -->
        <VCard
          id="faq"
          class="mb-6"
        >
          <VCardTitle class="text-h5 font-weight-bold d-flex align-center">
            <VIcon icon="mdi-help-circle" size="28" class="mr-3" color="primary" />
            14. Preguntas Frecuentes (FAQ)
          </VCardTitle>
          <VDivider />
          <VCardText>
            <h3 class="text-h6 font-weight-bold mb-3">
              ¿Qué hago si no veo un módulo en el menú?
            </h3>
            <p class="text-body-1 mb-4">
              Los módulos visibles dependen de los permisos asignados a su usuario. Si necesita acceso a un módulo específico, contacte al administrador del sistema para que revise sus permisos.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              ¿Cómo cambio mi contraseña?
            </h3>
            <p class="text-body-1 mb-4">
              Haga clic en su avatar (esquina superior derecha), seleccione "Perfil", luego "Cambiar Contraseña". Deberá ingresar su contraseña actual y la nueva contraseña dos veces.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              ¿Qué significa el estado "pendiente" en un cheque emitido?
            </h3>
            <p class="text-body-1 mb-4">
              El estado "Pendiente" indica que el cheque ha sido emitido al proveedor pero aún no ha sido cobrado. Cuando el proveedor cobre el cheque, debe actualizar manualmente el estado a "Cobrado".
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              ¿Puedo eliminar una venta ya registrada?
            </h3>
            <p class="text-body-1 mb-4">
              Por seguridad y trazabilidad contable, las ventas no pueden eliminarse una vez registradas. Si cometió un error, contacte al administrador o al área contable para que registren una corrección mediante una nota de crédito o cancelación.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              ¿Cómo exporto un reporte a Excel?
            </h3>
            <p class="text-body-1 mb-4">
              Después de generar un reporte con los filtros deseados, busque el botón "Exportar" o el ícono de Excel en la parte superior del reporte. Haga clic y el archivo se descargará automáticamente.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              ¿Qué hago si el sistema está lento?
            </h3>
            <p class="text-body-1 mb-4">
              Primero, verifique su conexión a Internet. Si el problema persiste:
            </p>
            <ul class="mb-4">
              <li>Cierre pestañas innecesarias del navegador</li>
              <li>Limpie el caché del navegador</li>
              <li>Intente con otro navegador (Chrome o Firefox recomendados)</li>
              <li>Si el problema continúa, contacte al soporte técnico</li>
            </ul>

            <h3 class="text-h6 font-weight-bold mb-3">
              ¿Cómo consulto el saldo de un cliente?
            </h3>
            <p class="text-body-1 mb-4">
              Vaya a "Clientes", busque al cliente en la lista y haga clic en el ícono del ojo (👁️) en la columna "Acciones". Se abrirá una vista detallada con el saldo actual, límite de crédito, crédito disponible e historial de movimientos.
            </p>

            <h3 class="text-h6 font-weight-bold mb-3">
              ¿Puedo usar el sistema desde mi celular?
            </h3>
            <p class="text-body-1 mb-4">
              Sí, el sistema es responsive y se adapta a dispositivos móviles. Sin embargo, para una mejor experiencia, especialmente al cargar ventas o generar reportes, se recomienda usar una computadora de escritorio o laptop.
            </p>

            <VAlert
              type="success"
              variant="tonal"
              class="mt-6"
            >
              <strong>¿Necesita más ayuda?</strong> Contacte al administrador del sistema o al soporte técnico de Maderas Pani.
            </VAlert>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.sticky-top {
  position: sticky;
  top: 80px;
  max-height: calc(100vh - 100px);
  overflow-y: auto;
}

.cursor-pointer {
  cursor: pointer;
}

/* Scroll suave para la navegación interna */
html {
  scroll-behavior: smooth;
}
</style>
