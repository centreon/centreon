import { mergeDeepRight, uniq } from 'ramda';
import { JsonDecoder } from 'ts.data.json';

import {
  type AnyFieldBlock,
  getAvailableBlocks,
  getDefaultValues
} from './blocks';
import type { FormValues, PlatformContext } from './models';

const isObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

// Every field an object writes, as a dotted path. A list, a null or an empty
// object is written as a whole.
const getLeafPaths = (
  value: unknown,
  path: Array<string> = []
): Array<string> =>
  isObject(value) && Object.keys(value).length > 0
    ? Object.entries(value).flatMap(([key, child]) =>
        getLeafPaths(child, [...path, key])
      )
    : path.length > 0
      ? [path.join('.')]
      : [];

const overlaps = (path: string, other: string): boolean =>
  path === other ||
  other.startsWith(`${path}.`) ||
  path.startsWith(`${other}.`);

// The fields more than one object writes: merged deeply, the last one would
// silently win.
export const getFieldsWrittenTwice = (
  objects: Array<unknown>
): Array<string> => {
  const pathsPerObject = objects.map((object) => getLeafPaths(object));

  return uniq(
    pathsPerObject.flatMap((paths, index) =>
      paths.filter((path) =>
        pathsPerObject
          .slice(index + 1)
          .some((others) => others.some((other) => overlaps(path, other)))
      )
    )
  );
};

/**
 * The fields two available blocks write in the defaults, in the payload of
 * `detail` read back, or in that detail decoded: each must be empty, since
 * the blocks' results are merged deeply.
 */
export const getFieldsOwnedTwice = ({
  blocks,
  context,
  detail
}: {
  blocks: ReadonlyArray<AnyFieldBlock>;
  context: PlatformContext;
  detail: unknown;
}): {
  defaultValues: Array<string>;
  detail: Array<string>;
  payload: Array<string>;
} => {
  const available = getAvailableBlocks(blocks, context);

  const decoded = available.map((block) => {
    const result = JsonDecoder.object(
      block.detailDecoders,
      'Block',
      block.detailKeyMap
    ).decode(detail);

    if (!result.isOk()) {
      throw new Error(result.error);
    }

    return result.value as FormValues;
  });

  const values = decoded.reduce<FormValues>(
    (merged, fields) => mergeDeepRight(merged, fields) as FormValues,
    getDefaultValues(blocks, context)
  );

  return {
    defaultValues: getFieldsWrittenTwice(
      available.map((block) => block.defaultValues)
    ),
    detail: getFieldsWrittenTwice(decoded),
    payload: getFieldsWrittenTwice(
      available.map((block) => block.toPayload(values, context))
    )
  };
};
