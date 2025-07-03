<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';

interface Props {
  user: {
    id: number;
    name: string;
    email: string;
    profile_photo_path?: string;
    profile_photo_url?: string;
    initials?: string;
  };
}

const props = defineProps<Props>();

const photoInput = ref<HTMLInputElement | null>(null);
const photoPreview = ref<string | null>(null);

const form = useForm({
  profile_photo: null as File | null,
  remove_photo: false as boolean,
  name: props.user.name,
  email: props.user.email,
});

const photoUrl = computed(() => {
  if (photoPreview.value) {
    return photoPreview.value;
  }
  return props.user.profile_photo_url || null;
});

const hasPhoto = computed(() => {
  return photoUrl.value && !form.remove_photo;
});

const selectNewPhoto = () => {
  photoInput.value?.click();
};

const updatePhotoPreview = () => {
  const photo = photoInput.value?.files?.[0];
  if (!photo) return;

  form.profile_photo = photo;
  form.remove_photo = false;

  const reader = new FileReader();
  reader.onload = (e) => {
    photoPreview.value = e.target?.result as string;
  };
  reader.readAsDataURL(photo);
};

const deletePhoto = () => {
  form.remove_photo = true;
  form.profile_photo = null;
  photoPreview.value = null;

  if (photoInput.value) {
    photoInput.value.value = '';
  }
};

const clearPhotoFileInput = () => {
  if (photoInput.value?.value) {
    photoInput.value.value = '';
  }
  form.profile_photo = null;
  photoPreview.value = null;
};

const updateProfile = () => {
  form.post(route('profile.update'), {
    preserveScroll: true,
    onSuccess: () => {
      clearPhotoFileInput();
    },
  });
};
</script>

<template>
  <section>
    <header>
      <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
        Profile Photo
      </h2>

      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        Add or update your profile photo.
      </p>
    </header>

    <form class="mt-6" @submit.prevent="updateProfile">
      <!-- Hidden file input -->
      <input
        ref="photoInput"
        type="file"
        class="hidden"
        accept="image/*"
        @change="updatePhotoPreview"
      />

      <!-- Current Photo / Preview -->
      <div class="flex items-center space-x-6">
        <!-- Photo Display -->
        <div class="relative">
          <div
            v-if="hasPhoto"
            class="h-20 w-20 rounded-full bg-gray-100 overflow-hidden"
          >
            <img
              v-if="photoUrl"
              :src="photoUrl"
              :alt="user.name"
              class="h-full w-full object-cover"
            />
          </div>

          <!-- Avatar fallback -->
          <div
            v-else
            class="h-20 w-20 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 text-lg font-medium dark:bg-gray-700 dark:text-gray-300"
          >
            {{ user.initials || user.name.substring(0, 2).toUpperCase() }}
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center space-x-2">
          <PrimaryButton
            type="button"
            :disabled="form.processing"
            @click="selectNewPhoto"
          >
            {{ hasPhoto ? 'Change Photo' : 'Upload Photo' }}
          </PrimaryButton>

          <SecondaryButton
            v-if="hasPhoto"
            type="button"
            :disabled="form.processing"
            @click="deletePhoto"
          >
            Remove
          </SecondaryButton>
        </div>
      </div>

      <!-- Upload guidelines -->
      <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
        <p>JPG, PNG, GIF o WEBP. Max 2MB. Min 100x100px.</p>
      </div>

      <!-- Error messages -->
      <InputError :message="form.errors.profile_photo" class="mt-2" />

      <!-- Submit button (only show if there are changes) -->
      <div
        v-if="form.profile_photo || form.remove_photo"
        class="flex items-center gap-4 mt-6"
      >
        <PrimaryButton :disabled="form.processing">
          Save Changes
        </PrimaryButton>

        <SecondaryButton
          type="button"
          :disabled="form.processing"
          @click="clearPhotoFileInput"
        >
          Cancel
        </SecondaryButton>

        <Transition
          enter-active-class="transition ease-in-out"
          enter-from-class="opacity-0"
          leave-active-class="transition ease-in-out"
          leave-to-class="opacity-0"
        >
          <p
            v-if="form.recentlySuccessful"
            class="text-sm text-gray-600 dark:text-gray-400"
          >
            Saved.
          </p>
        </Transition>
      </div>
    </form>
  </section>
</template>
