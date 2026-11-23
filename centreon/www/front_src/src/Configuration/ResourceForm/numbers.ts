import { JsonDecoder } from 'ts.data.json';
import { number } from 'yup';

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
