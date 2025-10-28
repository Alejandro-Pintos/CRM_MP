<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { layoutConfig } from '@layouts'
import { can } from '@layouts/plugins/casl'
import { useLayoutConfigStore } from '@layouts/stores/config'
// ❌ Ya no vamos a usar estos helpers para el `to` y el active
// import { getComputedNavLinkToProp, getDynamicI18nProps, isNavLinkActive } from '@layouts/utils'
import { getDynamicI18nProps } from '@layouts/utils'

const props = defineProps({
  item: {
    type: null,
    required: true,
  },
})

const router = useRouter()
const route = useRoute()

const configStore = useLayoutConfigStore()
const hideTitleAndBadge = configStore.isVerticalNavMini()

// ✅ Normaliza item.to: acepta string path "/x", string name "clientes" u objeto { name / path }
const normalizedTo = computed(() => {
  const to = props.item?.to
  if (!to) return null

  if (typeof to === 'string') {
    // si empieza con '/', es PATH → devolver string directamente
    if (to.startsWith('/')) return to
    // si no empieza con '/', asumimos que es NOMBRE de ruta
    return { name: to }
  }

  // si ya es objeto y trae path o name, lo respetamos
  if (to.path || to.name) return to

  return null
})

// ✅ Active exacto por path o por name
const isActive = computed(() => {
  if (!normalizedTo.value) return false
  const resolved = router.resolve(normalizedTo.value)
  return resolved.name === route.name || resolved.path === route.path
})

// ✅ Si el item es link externo (http/https), devolvemos href
const isExternal = computed(() => {
  const to = props.item?.to
  return typeof to === 'string' && /^https?:\/\//i.test(to)
})

// Props que vamos a bindear al link
const linkBind = computed(() => {
  if (isExternal.value) return { href: props.item.to, target: '_blank', rel: 'noopener' }
  if (normalizedTo.value) return { to: normalizedTo.value }
  return {} // sin navegación
})
</script>


<template>
  <li
    v-if="can(item.action, item.subject)"
    class="nav-link"
    :class="{ disabled: item.disable }"
  >
    <Component
      :is="isExternal ? 'a' : (normalizedTo ? 'RouterLink' : 'a')"
      v-bind="linkBind"
      :class="{ 'router-link-active router-link-exact-active': isActive }"
    >
      <Component
        :is="layoutConfig.app.iconRenderer || 'div'"
        v-bind="item.icon || layoutConfig.verticalNav.defaultNavItemIconProps"
        class="nav-item-icon"
      />

      <TransitionGroup name="transition-slide-x">
        <!-- 👉 Title -->
        <Component
          :is="layoutConfig.app.i18n.enable ? 'i18n-t' : 'span'"
          v-show="!hideTitleAndBadge"
          key="title"
          class="nav-item-title"
          v-bind="getDynamicI18nProps(item.title, 'span')"
        >
          {{ item.title }}
        </Component>

        <!-- 👉 Badge -->
        <Component
          :is="layoutConfig.app.i18n.enable ? 'i18n-t' : 'span'"
          v-if="item.badgeContent"
          v-show="!hideTitleAndBadge"
          key="badge"
          class="nav-item-badge"
          :class="item.badgeClass"
          v-bind="getDynamicI18nProps(item.badgeContent, 'span')"
        >
          {{ item.badgeContent }}
        </Component>
      </TransitionGroup>
    </Component>
  </li>
</template>


<style lang="scss">
.layout-vertical-nav {
  .nav-link a {
    display: flex;
    align-items: center;
  }
}
</style>
