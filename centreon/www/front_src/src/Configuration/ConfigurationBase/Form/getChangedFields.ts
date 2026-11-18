import { equals, isEmpty, keys } from 'ramda';

const isPlainObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

/**
 * The part of a payload that differs from the payload of the untouched form,
 * nested objects included. Lists are compared whole.
 *
 * Compares payloads rather than form values, so a field is left out exactly
 * when nothing would change in what the API receives.
 */
export const getChangedFields = (
  payload: Record<string, unknown>,
  untouchedPayload: Record<string, unknown>
): Record<string, unknown> =>
  keys(payload).reduce<Record<string, unknown>>((changed, key) => {
    const value = payload[key];
    const untouchedValue = untouchedPayload[key];

    if (isPlainObject(value) && isPlainObject(untouchedValue)) {
      const nested = getChangedFields(value, untouchedValue);

      return isEmpty(nested) ? changed : { ...changed, [key]: nested };
    }

    return equals(value, untouchedValue)
      ? changed
      : { ...changed, [key]: value };
  }, {});
