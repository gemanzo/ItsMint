# Modal System Guide

Il nuovo sistema di modal di ItsMint segue il principio DRY (Don't Repeat Yourself) e fornisce componenti base riutilizzabili per diverse situazioni.

## Struttura del Sistema

### Componenti Base

#### 1. BaseModal
Il componente fondamentale che standardizza tutto il comportamento dei modal.

**Features:**
- Animazioni consistenti con HeadlessUI
- Slot flessibili (header, icon, content, footer)
- Controllo delle dimensioni
- Gestione della chiusura
- Supporto per dark mode

**Props:**
```typescript
interface Props {
  isOpen: boolean
  title?: string
  subtitle?: string
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  closeable?: boolean
  showCloseButton?: boolean
}
```

#### 2. FormModal
Estende BaseModal per i form, aggiungendo gestione submit e loading states.

**Features:**
- Submit automatico con preventDefault
- Loading state con spinner
- Validazione con disabilitazione bottone
- Bottoni Cancel/Submit predefiniti

**Props:**
```typescript
interface Props {
  isOpen: boolean
  title: string
  subtitle?: string
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  isLoading?: boolean
  canSubmit?: boolean
  submitText?: string
  cancelText?: string
  loadingText?: string
}
```

#### 3. ConfirmationModal
Specializzato per conferme con diversi tipi (danger, warning, info, success).

**Features:**
- Icone colorate per tipo
- Bottoni appropriati per tipo (DangerButton per type="danger")
- Slot per contenuto aggiuntivo (es. input di conferma)
- Loading state

**Props:**
```typescript
interface Props {
  isOpen: boolean
  title: string
  message: string
  type?: 'danger' | 'warning' | 'info' | 'success'
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  isLoading?: boolean
  canConfirm?: boolean
  confirmText?: string
  cancelText?: string
  loadingText?: string
}
```

## Esempi di Utilizzo

### 1. Modal Base Semplice

```vue
<template>
  <BaseModal
    :is-open="showModal"
    title="Simple Modal"
    subtitle="This is a basic modal"
    @close="showModal = false"
  >
    <p>Your content here</p>
    
    <template #footer>
      <button @click="showModal = false">Close</button>
    </template>
  </BaseModal>
</template>
```

### 2. Form Modal

```vue
<template>
  <FormModal
    :is-open="showModal"
    title="Create User"
    :is-loading="isLoading"
    :can-submit="isFormValid"
    submit-text="Create User"
    @close="showModal = false"
    @submit="handleSubmit"
  >
    <div>
      <label>Name</label>
      <input v-model="form.name" />
    </div>
    
    <div>
      <label>Email</label>
      <input v-model="form.email" type="email" />
    </div>
  </FormModal>
</template>

<script setup>
import { reactive, computed, ref } from 'vue'
import FormModal from '@/Components/Base/FormModal.vue'

const showModal = ref(false)
const isLoading = ref(false)
const form = reactive({ name: '', email: '' })

const isFormValid = computed(() => 
  form.name.length > 0 && form.email.includes('@')
)

const handleSubmit = async () => {
  isLoading.value = true
  try {
    // API call
    showModal.value = false
  } finally {
    isLoading.value = false
  }
}
</script>
```

### 3. Confirmation Modal

```vue
<template>
  <ConfirmationModal
    :is-open="showDeleteModal"
    title="Delete User"
    message="Are you sure you want to delete this user? This action cannot be undone."
    type="danger"
    :is-loading="isDeleting"
    confirm-text="Delete User"
    @close="showDeleteModal = false"
    @confirm="handleDelete"
  />
</template>
```

### 4. Confirmation Modal con Contenuto Aggiuntivo

```vue
<template>
  <ConfirmationModal
    :is-open="showDeleteModal"
    title="Delete Portfolio"
    :message="`Type '${portfolioName}' to confirm deletion.`"
    type="danger"
    :is-loading="isDeleting"
    :can-confirm="isNameValid"
    @close="showDeleteModal = false"
    @confirm="handleDelete"
  >
    <div>
      <input 
        v-model="confirmationText" 
        :placeholder="portfolioName"
        class="w-full"
      />
    </div>
  </ConfirmationModal>
</template>

<script setup>
import { ref, computed } from 'vue'

const confirmationText = ref('')
const portfolioName = 'My Portfolio'

const isNameValid = computed(() => 
  confirmationText.value === portfolioName
)
</script>
```

## Pattern di Migrazione

### Da Modal Personalizzato a Sistema Base

**Prima:**
```vue
<template>
  <TransitionRoot :show="isOpen">
    <Dialog @close="close">
      <DialogPanel>
        <DialogTitle>My Title</DialogTitle>
        <!-- Complex structure -->
      </DialogPanel>
    </Dialog>
  </TransitionRoot>
</template>
```

**Dopo:**
```vue
<template>
  <BaseModal :is-open="isOpen" title="My Title" @close="close">
    <!-- Only your unique content -->
  </BaseModal>
</template>
```

### Sostituzione Modal per Form

**Prima:**
```vue
<form @submit.prevent="submit">
  <!-- form fields -->
  <div class="flex justify-end">
    <button @click="close">Cancel</button>
    <button type="submit" :disabled="isLoading">Submit</button>
  </div>
</form>
```

**Dopo:**
```vue
<FormModal 
  :is-loading="isLoading" 
  @submit="submit" 
  @close="close"
>
  <!-- only form fields -->
</FormModal>
```

## Best Practices

1. **Usa sempre BaseModal come punto di partenza** - anche se pensi di non averne bisogno
2. **FormModal per tutti i form** - gestisce automaticamente loading e submit
3. **ConfirmationModal per azioni distruttive** - con type="danger" per eliminazioni
4. **Slot per personalizzazioni** - usa gli slot quando il comportamento base non basta
5. **Consistent naming** - `isOpen` invece di `show`, `@close` invece di `@cancel`
6. **Loading states** - sempre gestire loading per UX migliore
7. **Validation** - usa `canSubmit`/`canConfirm` per disabilitare azioni

## Migrazione Graduale

Il sistema supporta migrazione graduale:
1. Identifica pattern comuni nei modal esistenti
2. Sostituisci uno alla volta con i componenti base
3. Testa ogni sostituzione individualmente
4. Rimuovi i modal obsoleti quando tutto è migrato

## Benefici

- **Meno codice duplicato** - struttura base riutilizzabile
- **Consistenza UI/UX** - stesso look & feel ovunque
- **Manutenibilità** - modifica in un posto, effetto globale
- **Accessibilità** - HeadlessUI garantisce supporto screen reader
- **Performance** - meno bundle duplicato
- **DX migliore** - API più semplici e prevedibili 