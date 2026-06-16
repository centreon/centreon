import axios from 'axios';

interface ApiErrorBody {
  code?: number;
  detail?: string;
  'hydra:description'?: string;
  message?: string;
}

/**
 * The provider test/discovery endpoints surface the real reason (e.g. an
 * invalid key) in the error body. The new API returns `{ code, message }`;
 * `detail` / `hydra:description` are checked too as a safety net for other
 * API Platform error shapes. Returns null when no usable message is present,
 * so callers can fall back to a generic label.
 */
export const getApiErrorMessage = (error: unknown): string | null => {
  if (!axios.isAxiosError(error)) {
    return null;
  }

  const data = error.response?.data as ApiErrorBody | undefined;

  return data?.message || data?.detail || data?.['hydra:description'] || null;
};
