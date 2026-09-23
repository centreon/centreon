import type { ReactElement } from 'react';

export interface SortableRowAction {
  /** Tailwind classes for the button, e.g. `text-primary-main`. */
  className?: string;
  icon: ReactElement;
  id: string;
  isDisabled?: boolean;
  label: string;
  onClick: () => void;
}

export interface RowParams<T> {
  index: number;
  value: T;
  values: ReadonlyArray<T>;
}

export interface RenderRowParams<T> extends RowParams<T> {
  setField: <K extends keyof T>(field: K, fieldValue: T[K]) => void;
  setValue: (value: T) => void;
}
