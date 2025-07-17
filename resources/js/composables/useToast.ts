import { ref, reactive } from 'vue';

export interface Toast {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  title: string;
  message?: string;
  duration?: number;
  persistent?: boolean;
}

interface ToastState {
  toasts: Toast[];
}

const state = reactive<ToastState>({
  toasts: [],
});

let toastIdCounter = 0;

export function useToast() {
  const generateId = (): string => {
    return `toast-${++toastIdCounter}-${Date.now()}`;
  };

  const addToast = (toast: Omit<Toast, 'id'>): string => {
    const id = generateId();
    const newToast: Toast = {
      id,
      duration: 5000, // 5 seconds default
      persistent: false,
      ...toast,
    };

    state.toasts.push(newToast);

    // Auto-remove toast after duration (unless persistent)
    if (!newToast.persistent && newToast.duration && newToast.duration > 0) {
      setTimeout(() => {
        removeToast(id);
      }, newToast.duration);
    }

    return id;
  };

  const removeToast = (id: string): void => {
    const index = state.toasts.findIndex((toast) => toast.id === id);
    if (index > -1) {
      state.toasts.splice(index, 1);
    }
  };

  const clearAllToasts = (): void => {
    state.toasts.splice(0);
  };

  // Convenience methods for different toast types
  const success = (
    title: string,
    message?: string,
    options?: Partial<Toast>
  ): string => {
    return addToast({
      type: 'success',
      title,
      message,
      ...options,
    });
  };

  const error = (
    title: string,
    message?: string,
    options?: Partial<Toast>
  ): string => {
    return addToast({
      type: 'error',
      title,
      message,
      duration: 7000, // Longer duration for errors
      ...options,
    });
  };

  const warning = (
    title: string,
    message?: string,
    options?: Partial<Toast>
  ): string => {
    return addToast({
      type: 'warning',
      title,
      message,
      duration: 6000,
      ...options,
    });
  };

  const info = (
    title: string,
    message?: string,
    options?: Partial<Toast>
  ): string => {
    return addToast({
      type: 'info',
      title,
      message,
      ...options,
    });
  };

  return {
    toasts: state.toasts,
    addToast,
    removeToast,
    clearAllToasts,
    success,
    error,
    warning,
    info,
  };
}
