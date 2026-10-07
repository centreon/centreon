import { mergeAll } from 'ramda';

import { getAvailableSections, type PlatformContext } from './sections';

export const getDefaultValues = (context: PlatformContext) =>
  mergeAll(
    getAvailableSections(context).map(({ section }) => section.defaultValues)
  );
