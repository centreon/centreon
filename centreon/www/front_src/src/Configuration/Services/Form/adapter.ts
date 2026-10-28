import { mergeAll } from 'ramda';

import {
  type FormValues,
  getAvailableSections,
  type PlatformContext
} from './sections';

// The create and update body: each section turns its own values into its keys.
export const getAdapter = (context: PlatformContext) => (data: unknown) =>
  mergeAll(
    getAvailableSections(context).map(({ section }) =>
      section.toPayload(data as FormValues, context)
    )
  );
