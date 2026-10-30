import { mergeDeepRight } from 'ramda';
import { err, JsonDecoder, ok } from 'ts.data.json';
import { ObjectSchema, type ObjectShape } from 'yup';

import type {
  Blocks,
  FieldBlock,
  FormValues,
  PlatformContext,
  SectionContext
} from './models';

// Any block, whatever the fields it reads back.
// biome-ignore lint/suspicious/noExplicitAny: a list of blocks mixes details.
export type AnyFieldBlock = FieldBlock<any>;

// Two blocks may fill keys of one object, as the notification recipients and
// delays do in `notifications`.
const mergeDeep = <Merged extends object>(
  objects: Array<Record<string, unknown>>
): Merged =>
  objects.reduce<Record<string, unknown>>(
    (merged, object) => mergeDeepRight(merged, object),
    {}
  ) as Merged;

export const getAvailableBlocks = <Block extends AnyFieldBlock>(
  blocks: ReadonlyArray<Block>,
  context: PlatformContext
): Array<Block> =>
  blocks.filter((block) => block.isAvailable?.(context) ?? true);

// Every input the available blocks hand over. No two blocks name the same
// input block (the page's `layout.test.ts`).
export const getInputBlocks = (
  blocks: ReadonlyArray<AnyFieldBlock>,
  context: SectionContext
): Blocks =>
  Object.assign(
    {},
    ...getAvailableBlocks(blocks, context).map((block) =>
      block.getInputs(context)
    )
  );

export const getDefaultValues = (
  blocks: ReadonlyArray<AnyFieldBlock>,
  context: PlatformContext
): FormValues =>
  mergeDeep(
    getAvailableBlocks(blocks, context).map((block) => block.defaultValues)
  );

// Two object rules on one key, such as `dataProcessing` from the freshness and
// the flapping blocks, check the fields of both.
const mergeShapes = (shapes: Array<ObjectShape>): ObjectShape =>
  shapes.reduce<ObjectShape>((merged, shape) => {
    const next = { ...merged };

    Object.entries(shape).forEach(([key, schema]) => {
      const current = next[key];

      next[key] =
        current instanceof ObjectSchema && schema instanceof ObjectSchema
          ? current.concat(schema)
          : schema;
    });

    return next;
  }, {});

export const getSchemaShape = (
  blocks: ReadonlyArray<AnyFieldBlock>,
  context: SectionContext
): ObjectShape =>
  mergeShapes(
    getAvailableBlocks(blocks, context).map((block) => block.getSchema(context))
  );

// The create and update body: each block turns its own values into its keys.
export const getPayload = (
  blocks: ReadonlyArray<AnyFieldBlock>,
  values: FormValues,
  context: PlatformContext
): Record<string, unknown> =>
  mergeDeep(
    getAvailableBlocks(blocks, context).map((block) =>
      block.toPayload(values, context)
    )
  );

// The detail of one resource, each block reading its own fields of it. The
// first block that fails fails the whole detail, as a single decoder would.
export const getDetailDecoder = <Detail extends object>({
  blocks,
  context,
  name
}: {
  blocks: ReadonlyArray<AnyFieldBlock>;
  context: PlatformContext;
  name: string;
}): JsonDecoder.Decoder<Detail> => {
  const decoders = getAvailableBlocks(blocks, context).map((block) =>
    JsonDecoder.object(block.detailDecoders, name, block.detailKeyMap)
  );

  return new JsonDecoder.Decoder<Detail>((json) => {
    const decoded: Array<Record<string, unknown>> = [];

    for (const decoder of decoders) {
      const result = decoder.decode(json);

      if (!result.isOk()) {
        return err<Detail>(result.error);
      }

      decoded.push(result.value as Record<string, unknown>);
    }

    return ok<Detail>(mergeDeep<Detail>(decoded));
  });
};
