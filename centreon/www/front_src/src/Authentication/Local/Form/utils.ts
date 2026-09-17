// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { path, props, split } from 'ramda';

interface GetFieldProps {
  field: string;
  object: Record<string, unknown>;
}

export const getField = <T>({ field, object }: GetFieldProps): T =>
  path(split('.', field), object) as T;

interface GetFieldsProps {
  fields: Array<string>;
  object: Record<string, unknown>;
}

export const getFields = <T>({ fields, object }: GetFieldsProps): Array<T> =>
  props<string, T>(fields, object);
