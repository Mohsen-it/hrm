<script setup>
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import Avatar from '@/Components/ui/Avatar.vue';

const props = defineProps({
  user: { type: Object, default: null },
  isCollapsed: { type: Boolean, default: false },
});

const { t, isRtl } = useTranslations();

const displayName = computed(() => props.user?.name || props.user?.email || '?');

const initials = computed(() => {
  if (!props.user) return '?';
  const name = props.user.name || props.user.email || '?';
  return name.charAt(0).toUpperCase();
});
</script>

<template>
  <div class="nav-user" :class="{ 'nav-user--collapsed': isCollapsed }">
    <!-- Avatar: photo on every page (lazy + cached static file), initials fallback -->
    <div class="nav-user__avatar nav-user__avatar--photo">
      <Avatar
        :name="displayName"
        :src="user?.avatar_url || null"
        size="sm"
        :dir="isRtl ? 'rtl' : 'ltr'"
      />
    </div>

    <!-- Info -->
    <div v-if="!isCollapsed" class="nav-user__info">
      <span class="nav-user__name">{{ user?.name || user?.email }}</span>
      <span class="nav-user__email">{{ user?.email }}</span>
    </div>
  </div>
</template>
