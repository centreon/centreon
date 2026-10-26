import { mergeDeepRight } from 'ramda';

import {
  type FormValues,
  getAvailableSections,
  type PlatformContext
} from './sections';

// Merged deeply: two sections may write keys of one API object, as Monitoring
// engine and Advanced do in `extended_informations`.
const mergePayloads = (
  payloads: Array<Record<string, unknown>>
): Record<string, unknown> =>
  payloads.reduce<Record<string, unknown>>(
    (merged, payload) => mergeDeepRight(merged, payload),
    {}
  );

// Each available section's slice of the body, merged.
export const getSectionsPayload = (
  values: FormValues,
  context: PlatformContext
): Record<string, unknown> =>
  mergePayloads(
    getAvailableSections(context).map(({ section }) =>
      section.toPayload(values, context)
    )
  );

// The create and update body: each section turns its own values into its keys.
export const getAdapter = (context: PlatformContext) => (data: unknown) =>
  getSectionsPayload(data as FormValues, context);
