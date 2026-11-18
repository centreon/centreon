import type { TFunction } from 'i18next';
import { path as getPath, values as getValues, includes, propOr } from 'ramda';

import {
  getMassChangeAdapter as getBlocksMassChangeAdapter,
  getMassChangeDefaultValues as getBlocksMassChangeDefaultValues,
  getMassChangeWarning as getBlocksMassChangeWarning
} from '../../ResourceForm/massChange';
import type { FormValues } from '../../ResourceForm/models';
import { labelNone } from '../../ResourceForm/translatedLabels';
import {
  labelNoneNeedsReplacement,
  labelReplacementOverwritesLists
} from '../translatedLabels';
import { blocks, type PlatformContext } from './sections';

export const getMassChangeDefaultValues = (context: PlatformContext) =>
  getBlocksMassChangeDefaultValues(blocks, context);

const getModes = (values: FormValues): Record<string, string> =>
  propOr({}, 'massChangeModes', values) as Record<string, string>;

// A list set to Replacement overwrites, or empties, that of every selected
// service.
const getReplacementWarning = (t: TFunction, values: FormValues) =>
  includes('replacement', getValues(getModes(values)))
    ? t(labelReplacementOverwritesLists)
    : null;

// None excludes every other option: added to a service that has some, the
// API refuses it.
const getNoneWarning = (t: TFunction, values: FormValues) =>
  includes(
    labelNone,
    (getPath(['notifications', 'options'], values) as Array<string>) ?? []
  ) && getModes(values).notificationOptions !== 'replacement'
    ? t(labelNoneNeedsReplacement)
    : null;

export const getMassChangeWarning =
  (t: TFunction) =>
  (values: FormValues): string | null =>
    [
      getBlocksMassChangeWarning(blocks)(t)(values),
      getNoneWarning(t, values),
      getReplacementWarning(t, values)
    ]
      .filter(Boolean)
      .join(' ') || null;

export const getMassChangeAdapter = (context: PlatformContext) =>
  getBlocksMassChangeAdapter(blocks, context);
