import { ref } from 'vue';

export function useApiError() {
  const error = ref<string>('');
  const isLoading = ref<boolean>(false);

  const handleError = (err: unknown): string => {
    console.error('API Error:', err);

    const errorMessage =
      err instanceof Error ? err.message : 'An unknown error occurred.';
    const axiosError = err as { response?: { data?: { message?: string } } };

    return axiosError.response?.data?.message || errorMessage;
  };

  const setError = (message: string) => {
    error.value = message;
  };

  const clearError = () => {
    error.value = '';
  };

  const setLoading = (loading: boolean) => {
    isLoading.value = loading;
  };

  const withLoading = async <T>(operation: () => Promise<T>): Promise<T> => {
    setLoading(true);
    clearError();

    try {
      const result = await operation();
      return result;
    } catch (err) {
      const errorMessage = handleError(err);
      setError(errorMessage);
      throw err;
    } finally {
      setLoading(false);
    }
  };

  return {
    error,
    isLoading,
    handleError,
    setError,
    clearError,
    setLoading,
    withLoading,
  };
}
