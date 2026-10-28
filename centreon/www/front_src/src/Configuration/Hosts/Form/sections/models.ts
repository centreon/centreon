import type { InputPropsWithoutGroup } from '@centreon/ui';

import type { TFunction } from 'i18next';
import type { JsonDecoder } from 'ts.data.json';
import type { ObjectShape } from 'yup';

export interface PlatformContext {
  isAdditiveInheritanceEnabled?: boolean;
  isCloudPlatform: boolean;
  // The form edits a selection: no per-host field, nothing required.
  isMassChange?: boolean;
}

export interface SectionContext extends PlatformContext {
  t: TFunction;
}

export type FormValues = Record<string, unknown>;

// The pieces of the form a section hands over, each one or more inputs that
// stay together. Where each piece is shown is `../layout.ts`'s business alone.
export type BlockId =
  | 'acknowledgmentTimeout'
  | 'basicInformation'
  | 'categories'
  | 'checkCommand'
  | 'childHosts'
  | 'comment'
  | 'eventHandler'
  | 'flapping'
  | 'freshness'
  | 'groups'
  | 'hostDetailsLeft'
  | 'hostDetailsRight'
  | 'macros'
  | 'massChangePoller'
  | 'notificationDelays'
  | 'notificationRecipients'
  | 'notifyOn'
  | 'parentHosts'
  | 'schedulingChecks'
  | 'schedulingNumbers'
  | 'severity'
  | 'snmp'
  | 'templates'
  | 'timezone';

// A block the platform or the mass change leaves out is simply missing.
export type Blocks = Partial<Record<BlockId, InputPropsWithoutGroup>>;

// Everything one section of the data owns, from its inputs to its slice of the
// API. It is not what the form shows as a section: the layout places its
// blocks, so a field can move without splitting its slice of the payload.
export interface FormSection<TDetail extends object = Record<never, never>> {
  defaultValues: FormValues;
  detailDecoders: JsonDecoder.DecoderObject<TDetail>;
  detailKeyMap?: JsonDecoder.DecoderObjectKeyMap<TDetail>;
  getInputs: (context: SectionContext) => Blocks;
  getSchema: (context: SectionContext) => ObjectShape;
  // When false the section has no inputs, rules, defaults, payload or decoded
  // fields.
  isAvailable?: (context: PlatformContext) => boolean;
  toPayload: (
    values: FormValues,
    context: PlatformContext
  ) => Record<string, unknown>;
}
