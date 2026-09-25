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

// What a form surface needs to drive submit and reset from outside the form,
// where Formik's context does not reach.
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
  // The row the form was opened from. A surface that shows more than the
  // fields — the panel names the resource and toggles it — has it straight
  // away, without waiting for the detail endpoint.
  resource?: ResourceRow | null;
}
