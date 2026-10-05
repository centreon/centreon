import type { InputPropsWithoutGroup } from '@centreon/ui';

import type { TFunction } from 'i18next';
import type { JsonDecoder } from 'ts.data.json';
import type { ObjectShape } from 'yup';

export interface PlatformContext {
  isCloudPlatform: boolean;
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
  getInputs: (context: SectionContext) => Array<InputPropsWithoutGroup>;
  getSchema: (context: SectionContext) => ObjectShape;
  // When false the section has no group, inputs, rules, defaults, payload or
  // decoded fields.
  isAvailable?: (context: PlatformContext) => boolean;
  // Untranslated: it is also the group name.
  label: string;
  toPayload: (values: FormValues) => Record<string, unknown>;
}
