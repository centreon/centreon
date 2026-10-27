import type { InputPropsWithoutGroup } from '@centreon/ui';

import type { TFunction } from 'i18next';
import type { JsonDecoder } from 'ts.data.json';
import type { ObjectShape } from 'yup';

export interface PlatformContext {
  isAdditiveInheritanceEnabled?: boolean;
  isCloudPlatform: boolean;
  // The form edits a selection: no per-host field, nothing required.
  isMassChange?: boolean;
  // The form edits a host template: no address, poller or host relations,
  // the template's own fields instead.
  isTemplate?: boolean;
}

export interface SectionContext extends PlatformContext {
  t: TFunction;
}

export type FormValues = Record<string, unknown>;

// Everything one section of the form owns, from its inputs to its slice of
// the API.
export interface FormSection<TDetail extends object = Record<never, never>> {
  defaultValues: FormValues;
  detailDecoders: JsonDecoder.DecoderObject<TDetail>;
  detailKeyMap?: JsonDecoder.DecoderObjectKeyMap<TDetail>;
  // The detail fields one kind of resource has and the other does not, left
  // out of the decoder of the other one.
  hostOnlyFields?: ReadonlyArray<keyof TDetail>;
  templateOnlyFields?: ReadonlyArray<keyof TDetail>;
  getInputs: (context: SectionContext) => Array<InputPropsWithoutGroup>;
  getSchema: (context: SectionContext) => ObjectShape;
  // When false the section has no group, inputs, rules, defaults, payload or
  // decoded fields.
  isAvailable?: (context: PlatformContext) => boolean;
  // Untranslated: it is also the group name.
  label: string;
  toPayload: (
    values: FormValues,
    context: PlatformContext
  ) => Record<string, unknown>;
}
