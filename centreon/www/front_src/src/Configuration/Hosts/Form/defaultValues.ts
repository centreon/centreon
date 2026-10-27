import { mergeAll } from 'ramda';

import {
  getAvailableSections,
  getSectionDefaultValues,
  type PlatformContext
} from './sections';

export const getDefaultValues = (context: PlatformContext) =>
  mergeAll(
    getAvailableSections(context).map(({ section }) =>
      getSectionDefaultValues(section, context)
    )
  );
