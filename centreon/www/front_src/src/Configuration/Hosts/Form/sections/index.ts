import { dataProcessing } from './dataProcessing';
import { extendedInfos } from './extendedInfos';
import { hostConfiguration } from './hostConfiguration';
import type { FormSection, PlatformContext } from './models';
import { notification } from './notification';
import { relations } from './relations';

// The five sections of the US, in the order the form shows them. They also
// drive the pinned navigation, which the shared form renders on its own from
// four groups up.
export const sections = [
  hostConfiguration,
  notification,
  relations,
  dataProcessing,
  extendedInfos
] as const;

// Numbered before the platform filters them, so a section's group keeps its
// place whatever the others do.
export const getAvailableSections = (
  context: PlatformContext
): Array<{ order: number; section: (typeof sections)[number] }> =>
  sections
    .map((section, index) => ({ order: index + 1, section }))
    .filter(({ section }) => section.isAvailable?.(context) ?? true);

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
  FormSection,
  FormValues,
  PlatformContext,
  SectionContext
} from './models';
