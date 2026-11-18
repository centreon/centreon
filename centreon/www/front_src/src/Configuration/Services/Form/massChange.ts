import type { TFunction } from 'i18next';
import { values as getValues, includes, propOr } from 'ramda';

import {
  getMassChangeAdapter as getBlocksMassChangeAdapter,
  getMassChangeDefaultValues as getBlocksMassChangeDefaultValues,
  getMassChangeWarning as getBlocksMassChangeWarning
} from '../../ResourceForm/massChange';
import type { FormValues } from '../../ResourceForm/models';
import { labelReplacementOverwritesLists } from '../translatedLabels';
import { blocks, type PlatformContext } from './sections';

export const getMassChangeDefaultValues = (context: PlatformContext) =>
  getBlocksMassChangeDefaultValues(blocks, context);

// A list set to Replacement overwrites, or empties, that of every selected
// service.
export const getMassChangeWarning =
  (t: TFunction) =>
  (values: FormValues): string | null =>
    getBlocksMassChangeWarning(blocks)(t)(values) ??
    (includes(
      'replacement',
      getValues(propOr({}, 'massChangeModes', values) as object)
    )
      ? t(labelReplacementOverwritesLists)
      : null);

export const getMassChangeAdapter = (context: PlatformContext) =>
  getBlocksMassChangeAdapter(blocks, context);
