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

export interface FormState {
  id: number | null;
  isOpen: boolean;
  mode: 'add' | 'edit';
  // The row the form was opened from, for a surface that shows more than the
  // fields before the detail endpoint answers.
  resource?: ResourceRow | null;
}
