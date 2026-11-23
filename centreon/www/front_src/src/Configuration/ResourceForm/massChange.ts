import {
  assocPath,
  path as getPath,
  isNil,
  join,
  map,
  mergeDeepRight,
  pipe,
  reject
} from 'ramda';

import { getChangedFields } from '../ConfigurationBase/Form';
import {
  type AnyFieldBlock,
  getAvailableBlocks,
  getDefaultValues,
  getPayload
} from './blocks';
import type {
  FormValues,
  MassChangeWarningContext,
  PlatformContext,
  UpdateModePaths
} from './models';
import { type UpdateMode, updateModeSuffixes } from './updateMode';

type Path = ReadonlyArray<string>;

// Every list of the resource that offers an update mode, whatever the
// platform: the blocks declare theirs.
const getUpdateModeFields = (
  blocks: ReadonlyArray<AnyFieldBlock>
): UpdateModePaths =>
  Object.assign(
    {},
    ...blocks.map((block) => block.massChange?.updateModeFields ?? {})
  );

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

export const getMassChangeDefaultValues = (
  blocks: ReadonlyArray<AnyFieldBlock>,
  context: PlatformContext
): FormValues & { massChangeModes: Record<string, UpdateMode> } => {
  const defaultValues = getAvailableBlocks(blocks, context).reduce(
    (values, block) =>
      block.massChange?.defaultValues
        ? (mergeDeepRight(values, block.massChange.defaultValues) as FormValues)
        : values,
    clearTriStates(getDefaultValues(blocks, context)) as FormValues
  );

  return {
    ...defaultValues,
    massChangeModes: map(
      (): UpdateMode => 'incremental',
      getUpdateModeFields(blocks)
    )
  };
};

// The warnings the blocks raise for the values.
export const getBlockWarnings = ({
  blocks,
  t,
  values
}: MassChangeWarningContext & {
  blocks: ReadonlyArray<AnyFieldBlock>;
}): Array<string | null> =>
  blocks.map((block) => block.massChange?.getWarning?.({ t, values }) ?? null);

// One confirmation text out of the warnings raised, or none.
export const joinWarnings: (warnings: Array<string | null>) => string | null =
  pipe(reject(isNil), join(' '), (text: string) => text || null);

const appendToKey = (path: Path, suffix: string) => [
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

/**
 * The body sent to every selected resource: what the user changed, plus every
 * list set to Replacement — `x` replaces, `x_to_add` adds.
 */
export const getMassChangeAdapter = (
  blocks: ReadonlyArray<AnyFieldBlock>,
  context: PlatformContext
) => {
  const toPayload = (values: FormValues) => getPayload(blocks, values, context);
  const updateModeFields = getUpdateModeFields(blocks);

  const { massChangeModes: _modes, ...untouchedValues } =
    getMassChangeDefaultValues(blocks, context);
  const untouchedPayload = toPayload(untouchedValues);

  return (values: FormValues): object => {
    const { massChangeModes, ...fields } = values as {
      massChangeModes: Record<string, UpdateMode>;
    } & FormValues;

    const payload = toPayload(fields);

    return Object.entries(updateModeFields).reduce(
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
            appendToKey(
              path,
              updateModeSuffixes[massChangeModes[field] ?? 'incremental']
            ),
            getPath([...path], current),
            dissocPathIn(path, current)
          );
        }, changed),
      getChangedFields(payload, untouchedPayload)
    );
  };
};
