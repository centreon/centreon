import { ResourceRow } from '../models';

export type ListMeta = {
  limit: number;
  page: number;
  total: number;
};
export type List<TEntity> = {
  meta: ListMeta;
  result: Array<TEntity>;
};

// Drives submit and reset from outside the form, where Formik does not reach.
export interface FormActions {
  canReset: boolean;
  canSubmit: boolean;
  isSubmitting: boolean;
  reset: () => void;
  submit: () => void;
}

export type FormMode = 'add' | 'edit' | 'massChange';

export interface FormState {
  id: number | null;
  // The rows a mass change applies to.
  selection?: Array<{ id: number; name: string }>;
  isOpen: boolean;
  mode: FormMode;
  // The row the form was opened from, for a surface that shows more than the
  // fields before the detail endpoint answers.
  resource?: ResourceRow | null;
}
