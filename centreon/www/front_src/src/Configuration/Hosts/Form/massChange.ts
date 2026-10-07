import { assocPath, path as getPath, map } from 'ramda';

import { getChangedFields } from '../../ConfigurationBase/Form';
import { getDefaultValues } from './defaultValues';
import { getAvailableSections, type PlatformContext } from './sections';
import {
  type UpdateMode,
  type UpdateModeField,
  updateModeFields
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

export const getMassChangeDefaultValues = (context: PlatformContext) => ({
  ...(clearTriStates(getDefaultValues(context)) as Record<string, unknown>),
  createServicesLinkedToTemplates: false,
  massChangeModes: map(
    (): UpdateMode => 'incremental',
    updateModeFields
  ) as Record<UpdateModeField, UpdateMode>
});

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
 * The body sent to every selected host: only what the user changed, in the
 * shape PATCH v2 plans — `x` replaces, `x_to_add` adds, and macros are
 * upserted as legacy mass change does.
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

    const withModes = (
      Object.entries(updateModeFields) as Array<
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

    const macrosPath = ['check_options', 'macros'];

    return hasPath(macrosPath, withModes)
      ? assocPath(
          appendToKey(macrosPath, '_to_upsert'),
          getPath(macrosPath, withModes),
          dissocPathIn(macrosPath, withModes)
        )
      : withModes;
  };
};
