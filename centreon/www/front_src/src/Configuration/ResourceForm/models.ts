import type { InputPropsWithoutGroup } from '@centreon/ui';

import type { TFunction } from 'i18next';
import type { JsonDecoder } from 'ts.data.json';
import type { ObjectShape } from 'yup';

export type NamedEntity = {
  id: number;
  name: string;
};

export type Icon = NamedEntity & {
  url: string;
};

export interface PlatformContext {
  isAdditiveInheritanceEnabled?: boolean;
  isCloudPlatform: boolean;
  // The form edits a selection: no per-resource field, nothing required.
  isMassChange?: boolean;
}

export interface SectionContext extends PlatformContext {
  t: TFunction;
}

export type FormValues = Record<string, unknown>;

// What a block's inputs share whatever the resource: where its selectors read
// and how its test ids start (`host-form`, `service-form`).
export interface ResourceOptions {
  baseEndpoint: string;
  idPrefix: string;
}

// The pieces of a form a field block hands over, each one or more inputs that
// stay together. Where each piece is shown is the page's `layout.ts` alone.
export type BlockId =
  | 'acknowledgmentTimeout'
  | 'actionUrlAndGeoCoordinates'
  | 'activeAndPassiveChecks'
  | 'basicInformation'
  | 'categories'
  | 'checkCommand'
  | 'checkPeriodAndIntervals'
  | 'childHosts'
  | 'comment'
  | 'eventHandler'
  | 'flapping'
  | 'freshness'
  | 'groups'
  | 'macros'
  | 'notesAndIcons'
  | 'notificationDelays'
  | 'notificationRecipients'
  | 'notifyOn'
  | 'parentHosts'
  | 'poller'
  | 'severity'
  | 'snmp'
  | 'templates'
  | 'timezone';

// A block the platform or the mass change leaves out is simply missing.
export type Blocks = Partial<Record<BlockId, InputPropsWithoutGroup>>;

// Where a list lands in the payload, for each list a mass change offers an
// update mode for: one choice may drive several keys.
export type UpdateModePaths = Record<
  string,
  ReadonlyArray<ReadonlyArray<string>>
>;

// What a block adds to a mass change of the resource.
export interface BlockMassChange {
  // Laid over the defaults once their tri-states are cleared, for the fields
  // that have no "unchanged" of their own, such as a switch.
  defaultValues?: FormValues;
  // The confirmation's warning, when the values ask for one.
  getWarning?: (t: TFunction) => (values: FormValues) => string | null;
  updateModeFields?: UpdateModePaths;
}

// Everything one block of fields owns, from its inputs to its slice of the
// API. Blocks may share a form key and an API object (`notifications`,
// `dataProcessing`…): their defaults, decoded fields, schemas and payloads are
// merged deeply, so none of them names a field another one owns.
export interface FieldBlock<TDetail extends object = Record<never, never>> {
  defaultValues: FormValues;
  detailDecoders: JsonDecoder.DecoderObject<TDetail>;
  detailKeyMap?: JsonDecoder.DecoderObjectKeyMap<TDetail>;
  getInputs: (context: SectionContext) => Blocks;
  getSchema: (context: SectionContext) => ObjectShape;
  // When false the block has no inputs, rules, defaults, payload or decoded
  // fields.
  isAvailable?: (context: PlatformContext) => boolean;
  massChange?: BlockMassChange;
  toPayload: (
    values: FormValues,
    context: PlatformContext
  ) => Record<string, unknown>;
}

type UnionToIntersection<Union> = (
  Union extends unknown
    ? (union: Union) => void
    : never
) extends (intersection: infer Intersection) => void
  ? Intersection
  : never;

type DetailOf<Block> = Block extends FieldBlock<infer Detail> ? Detail : never;

// What a form opens on: the fields every block of `Blocks` reads back.
export type DetailOfBlocks<BlockList extends ReadonlyArray<unknown>> =
  UnionToIntersection<DetailOf<BlockList[number]>>;
