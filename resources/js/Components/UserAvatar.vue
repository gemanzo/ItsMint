<script setup lang="ts">
interface User {
  name: string;
  profile_photo_url?: string;
  initials?: string;
}

const props = withDefaults(
  defineProps<{
    user: User;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    showTooltip?: boolean;
  }>(),
  {
    size: 'md',
    showTooltip: false,
  }
);

const sizeClasses: Record<'sm' | 'md' | 'lg' | 'xl', string> = {
  sm: 'h-8 w-8 text-sm',
  md: 'h-10 w-10 text-base',
  lg: 'h-12 w-12 text-lg',
  xl: 'h-16 w-16 text-xl',
};

function getInitials(name: string): string {
  const trimmed = name.trim();
  const parts = trimmed.split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return trimmed.substring(0, 2).toUpperCase();
}
</script>

<template>
  <div
    :class="[
      'rounded-full overflow-hidden flex-shrink-0',
      sizeClasses[props.size],
    ]"
    :title="props.showTooltip ? props.user.name : undefined"
  >
    <img
      v-if="props.user.profile_photo_url"
      :src="props.user.profile_photo_url"
      :alt="props.user.name"
      class="h-full w-full object-cover"
    />
    <div
      v-else
      :class="[
        'h-full w-full bg-gray-300 flex items-center justify-center text-gray-600 font-medium dark:bg-gray-700 dark:text-gray-300',
        sizeClasses[props.size],
      ]"
    >
      {{ props.user.initials || getInitials(props.user.name) }}
    </div>
  </div>
</template>
