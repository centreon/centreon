import type { TFunction } from 'i18next';
import {
  assocPath,
  equals,
  path as getPath,
  isNil,
  map,
  mergeDeepRight,
  values as objectValues
} from 'ramda';

import { getChangedFields } from '../../ConfigurationBase/Form';
import { labelReplacementOverwritesLists } from '../translatedLabels';
import { getSectionsPayload } from './adapter';
import { getDefaultValues } from './defaultValues';
import type { FormValues, PlatformContext } from './sections';
import {
  type UpdateMode,
  type UpdateModeField,
  updateModeFields,
  updateModeSuffixes
} from './updateMode';

type Path = ReadonlyArray<string>;

const isPlainObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

const isEmptyValue = (value: unknown): boolean =>
  value === '' || value === null;

// Every leaf of `value`, objects walked into, lists kept whole.
const getLeaves = (
  value: unknown,
  path: Path = []
): Array<{ path: Path; value: unknown }> =>
  isPlainObject(value)
    ? Object.entries(value).flatMap(([key, child]) =>
        getLeaves(child, [...path, key])
      )
    : [{ path, value }];

// The fields a user can empty: a text or number (`''`) or a single selector
// (`null`). Their mass-change value starts `undefined` instead, so a field
// emptied on purpose is told apart from one never touched.
const getClearableFields = (
  context: PlatformContext
): Array<{ empty: unknown; path: Path }> =>
  getLeaves(getDefaultValues(context))
    .filter(({ value }) => isEmptyValue(value))
    .map(({ path, value }) => ({ empty: value, path }));

// Tri-states start unselected: a mass change imposes nothing it was not told
// to (RG-H5).
const clearTriStates = (value: unknown): unknown => {
  if (value === 'use_default') {
    return null;
  }

  if (isPlainObject(value)) {
    return map(clearTriStates, value);
  }

  return value;
};

export const getMassChangeDefaultValues = (context: PlatformContext) => {
  const defaultValues = clearTriStates(
    getClearableFields(context).reduce<unknown>(
      (current, { path }) => assocPath([...path], undefined, current),
      getDefaultValues(context)
    )
  ) as Record<string, unknown>;

  return {
    ...defaultValues,
    // So are the switches, which have no "unchanged": Yes and No instead.
    ...(defaultValues.notifications !== undefined && {
      notifications: mergeDeepRight(defaultValues.notifications as object, {
        contactAdditiveInheritance: null,
        contactGroupAdditiveInheritance: null,
        useOnlyContactsFromHost: null
      })
    }),
    massChangeModes: map(
      (): UpdateMode => 'incremental',
      updateModeFields
    ) as Record<UpdateModeField, UpdateMode>
  };
};

// Replacement overwrites a list on every selected service, even with nothing:
// the confirmation says so.
export const getMassChangeWarning =
  (t: TFunction) =>
  (values: Record<string, unknown>): string | null =>
    objectValues(
      (values.massChangeModes ?? {}) as Record<UpdateModeField, UpdateMode>
    ).includes('replacement')
      ? t(labelReplacementOverwritesLists)
      : null;

const appendToKey = (path: Path, suffix: string): Array<string> => [
  ...path.slice(0, -1),
  `${path[path.length - 1]}${suffix}`
];

const dissocPathIn = (
  path: Path,
  payload: Record<string, unknown>
): Record<string, unknown> => {
  const parentPath = path.slice(0, -1);
  const parent = (
    parentPath.length ? getPath([...parentPath], payload) : payload
  ) as Record<string, unknown>;
  const { [path[path.length - 1]]: _removed, ...rest } = parent;

  return parentPath.length ? assocPath([...parentPath], rest, payload) : rest;
};

const hasPath = (path: Path, payload: object): boolean =>
  getPath([...path], payload) !== undefined;

// A value no form holds, of the kind the field takes: it moves exactly the
// payload keys the field feeds.
const getProbe = (empty: unknown): unknown =>
  empty === '' ? '1' : { id: -1, name: '' };

/**
 * The body sent to every selected service: what the user changed, a field
 * emptied on purpose as `null`, and each list under the key of its update
 * mode — `x_to_add` adds, `x` replaces, `x_to_remove` removes.
 */
export const getMassChangeAdapter = (context: PlatformContext) => {
  const toPayload = (values: FormValues) => getSectionsPayload(values, context);

  const clearableFields = getClearableFields(context);

  // The sections never see `undefined`: an untouched field reads as empty.
  const withEmptyFields = (values: FormValues): FormValues =>
    clearableFields.reduce(
      (current, { empty, path }) =>
        getPath([...path], current) === undefined
          ? (assocPath([...path], empty, current) as FormValues)
          : current,
      values
    );

  const { massChangeModes: _modes, ...untouchedValues } =
    getMassChangeDefaultValues(context);
  const untouchedPayload = toPayload(withEmptyFields(untouchedValues));

  // The payload keys the emptied fields feed, among those that hold `null`
  // when untouched: emptying never sends a list as `null`.
  const getClearedKeys = (
    fields: FormValues,
    payload: Record<string, unknown>
  ): Array<Path> =>
    clearableFields
      .filter(({ path }) => isEmptyValue(getPath([...path], fields)))
      .flatMap(({ empty, path }) => {
        const probedPayload = toPayload(
          assocPath([...path], getProbe(empty), withEmptyFields(fields))
        );

        return getLeaves(probedPayload)
          .filter(
            ({ path: key, value }) =>
              !equals(value, getPath([...key], payload)) &&
              getPath([...key], untouchedPayload) === null
          )
          .map(({ path: key }) => key);
      });

  return (values: Record<string, unknown>): object => {
    const { massChangeModes, ...fields } = values as {
      massChangeModes: Record<UpdateModeField, UpdateMode>;
    } & FormValues;

    const payload = toPayload(withEmptyFields(fields));

    const changed = getClearedKeys(fields, payload).reduce(
      (current, key) => assocPath([...key], null, current),
      getChangedFields(payload, untouchedPayload)
    );

    return (
      Object.entries(updateModeFields) as Array<
        [UpdateModeField, ReadonlyArray<Path>]
      >
    ).reduce(
      (current, [field, paths]) =>
        paths.reduce((body, path) => {
          if (!hasPath(path, payload)) {
            return body;
          }

          const mode = massChangeModes?.[field] ?? 'incremental';

          // Replacement applies even an empty list: that is how a mass change
          // empties one.
          if (equals(mode, 'replacement')) {
            return assocPath([...path], getPath([...path], payload), body);
          }

          if (isNil(getPath([...path], body))) {
            return body;
          }

          return assocPath(
            appendToKey(path, updateModeSuffixes[mode]),
            getPath([...path], body),
            dissocPathIn(path, body)
          );
        }, current),
      changed
    );
  };
};
