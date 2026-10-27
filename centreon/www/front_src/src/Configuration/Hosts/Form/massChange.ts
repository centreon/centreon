import type { TFunction } from 'i18next';
import { assocPath, path as getPath, map, mergeDeepRight } from 'ramda';

import { getChangedFields } from '../../ConfigurationBase/Form';
import { labelTemplateReplacementDeletesServices } from '../translatedLabels';
import { getDefaultValues } from './defaultValues';
import { getAvailableSections, type PlatformContext } from './sections';
import {
  getUpdateModeFields,
  type UpdateMode,
  type UpdateModeField
} from './updateMode';

// Tri-states start unselected: a mass change imposes nothing it was not told
// to (RG-H5).
const clearTriStates = (value: unknown): unknown => {
  if (value === 'use_default') {
    return null;
  }

  if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
    return map(clearTriStates, value as Record<string, unknown>);
  }

  return value;
};

export const getMassChangeDefaultValues = (context: PlatformContext) => {
  const defaultValues = clearTriStates(getDefaultValues(context)) as Record<
    string,
    unknown
  >;

  return {
    ...defaultValues,
    // So are the additive inheritances, a switch having no "unchanged".
    ...(defaultValues.notifications !== undefined && {
      notifications: mergeDeepRight(defaultValues.notifications as object, {
        contactAdditiveInheritance: null,
        contactGroupAdditiveInheritance: null
      })
    }),
    // A template creates no service.
    ...(!context.isTemplate && { createServicesLinkedToTemplates: false }),
    massChangeModes: map(
      (): UpdateMode => 'incremental',
      getUpdateModeFields(context)
    ) as Record<UpdateModeField, UpdateMode>
  };
};

// Replacing a host's templates deletes the services they created, on every
// selected host: the confirmation says so.
export const getMassChangeWarning =
  (t: TFunction) =>
  (values: Record<string, unknown>): string | null =>
    getPath(['massChangeModes', 'templates'], values) === 'replacement'
      ? t(labelTemplateReplacementDeletesServices)
      : null;

const appendToKey = (path: ReadonlyArray<string>, suffix: string) => [
  ...path.slice(0, -1),
  `${path[path.length - 1]}${suffix}`
];

const dissocPathIn = (
  path: ReadonlyArray<string>,
  payload: Record<string, unknown>
): Record<string, unknown> => {
  const parentPath = path.slice(0, -1);
  const parent = (
    parentPath.length ? getPath([...parentPath], payload) : payload
  ) as Record<string, unknown>;
  const { [path[path.length - 1]]: _removed, ...rest } = parent;

  return parentPath.length ? assocPath([...parentPath], rest, payload) : rest;
};

const hasPath = (path: ReadonlyArray<string>, payload: object): boolean =>
  getPath([...path], payload) !== undefined;

/**
 * The body sent to every selected host: what the user changed, plus every
 * list set to Replacement — `x` replaces, `x_to_add` adds.
 */
export const getMassChangeAdapter = (context: PlatformContext) => {
  const toPayload = (values: Record<string, unknown>) =>
    Object.assign(
      {},
      ...getAvailableSections(context).map(({ section }) =>
        section.toPayload(values, context)
      )
    ) as Record<string, unknown>;

  const { massChangeModes: _modes, ...untouchedValues } =
    getMassChangeDefaultValues(context);
  const untouchedPayload = toPayload(untouchedValues);

  return (values: Record<string, unknown>): object => {
    const { massChangeModes, ...fields } = values as {
      massChangeModes: Record<UpdateModeField, UpdateMode>;
    } & Record<string, unknown>;

    const payload = toPayload(fields);

    return (
      Object.entries(getUpdateModeFields(context)) as Array<
        [UpdateModeField, ReadonlyArray<ReadonlyArray<string>>]
      >
    ).reduce(
      (changed, [field, paths]) =>
        paths.reduce((current, path) => {
          if (!hasPath(path, payload)) {
            return current;
          }

          // Replacement applies even an empty list: that is how a mass change
          // clears one.
          if (massChangeModes[field] === 'replacement') {
            return assocPath([...path], getPath([...path], payload), current);
          }

          if (!hasPath(path, current)) {
            return current;
          }

          return assocPath(
            appendToKey(path, '_to_add'),
            getPath([...path], current),
            dissocPathIn(path, current)
          );
        }, changed),
      getChangedFields(payload, untouchedPayload)
    );
  };
};
