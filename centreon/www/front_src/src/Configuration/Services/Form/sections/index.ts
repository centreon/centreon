import { advanced } from './advanced';
import { checkOptions } from './checkOptions';
import { generalInformation } from './generalInformation';
import type { FormSection, PlatformContext } from './models';
import { monitoringEngine } from './monitoringEngine';
import { notifications } from './notifications';
import { relations } from './relations';

// The six sections of the Figma panel (MON-213119), in its order. They also
// drive the pinned navigation, which the shared form renders on its own from
// four groups up.
export const sections = [
  generalInformation,
  checkOptions,
  relations,
  monitoringEngine,
  notifications,
  advanced
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
export type ServiceDetail = UnionToIntersection<
  DetailOf<(typeof sections)[number]>
>;

export type {
  FormSection,
  FormValues,
  PlatformContext,
  SectionContext
} from './models';
