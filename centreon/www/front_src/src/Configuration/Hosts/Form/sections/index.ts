import { dataProcessing } from './dataProcessing';
import { extendedInfos } from './extendedInfos';
import { hostConfiguration } from './hostConfiguration';
import type {
  Blocks,
  FormSection,
  PlatformContext,
  SectionContext
} from './models';
import { notification } from './notification';
import { relations } from './relations';

// The five slices of the host the form edits. The sections it shows, and their
// order, are `../layout.ts`'s.
export const sections = [
  hostConfiguration,
  notification,
  relations,
  dataProcessing,
  extendedInfos
] as const;

export const getAvailableSections = (
  context: PlatformContext
): Array<{ section: (typeof sections)[number] }> =>
  sections
    .filter((section) => section.isAvailable?.(context) ?? true)
    .map((section) => ({ section }));

// Every block the available sections hand over. No two sections name the same
// block (`../layout.test.ts`).
export const getBlocks = (context: SectionContext): Blocks =>
  Object.assign(
    {},
    ...getAvailableSections(context).map(({ section }) =>
      section.getInputs(context)
    )
  );

type UnionToIntersection<Union> = (
  Union extends unknown
    ? (union: Union) => void
    : never
) extends (intersection: infer Intersection) => void
  ? Intersection
  : never;

type DetailOf<Section> =
  Section extends FormSection<infer Detail> ? Detail : never;

// What the form opens on: the fields every section reads back.
export type HostDetail = UnionToIntersection<
  DetailOf<(typeof sections)[number]>
>;

export type {
  BlockId,
  Blocks,
  FormSection,
  FormValues,
  PlatformContext,
  SectionContext
} from './models';
