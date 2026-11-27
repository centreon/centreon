import { InputType } from '@centreon/ui';

import { createElement } from 'react';
import { JsonDecoder } from 'ts.data.json';
import { number } from 'yup';

import type { FormValues } from './models';

// A number field holds `''` until something is typed.
export type OptionalNumber = number | '';

// The detail endpoints leave unset values out rather than sending null.
export const optionalNumberDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
).map((value): OptionalNumber => value ?? '');

export const toApiNumber = (
  value: OptionalNumber | undefined
): number | null =>
  value === '' || value === undefined ? null : Number(value);

// An optional integer of at least `min`, one message for every way to fail.
export const getIntegerSchema = ({
  message,
  min
}: {
  message: string;
  min: number;
}) =>
  number()
    .transform((value, originalValue) => (originalValue === '' ? null : value))
    .nullable()
    .typeError(message)
    .integer(message)
    .min(min, message);

// A number field of at least `min`, with the unit it counts in, such as
// seconds, shown after the value.
export const getNumberInput = ({
  dataTestId,
  fieldName,
  getDisabled,
  label,
  min,
  unit
}: {
  dataTestId: string;
  fieldName: string;
  getDisabled?: (values: FormValues) => boolean;
  label: string;
  min: number;
  unit?: string;
}) => ({
  dataTestId,
  fieldName,
  ...(getDisabled && { getDisabled }),
  label,
  text: {
    ...(unit && { endAdornment: createElement('span', null, unit) }),
    min,
    type: 'number'
  },
  type: InputType.Text
});
