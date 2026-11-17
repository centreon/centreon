import { type InputPropsWithoutGroup, InputType } from '@centreon/ui';

import type { ComponentType } from 'react';
import { JsonDecoder } from 'ts.data.json';
import { type ObjectShape, object } from 'yup';

import {
  getMacrosSchema,
  type MacroRow,
  type MacrosSchemaConfig,
  macroDecoder,
  toMacrosPayload
} from '../../ConfigurationBase/Form/Macros';
import type {
  FieldBlock,
  PlatformContext,
  ResourceOptions,
  SectionContext
} from '../models';
import { labelCustomMacros } from '../translatedLabels';

type MacrosSchema = ReturnType<typeof getMacrosSchema>;

// Within the API's `check_options`, which the check command fills too.
interface MacrosDetail {
  checkOptions: { macros: Array<MacroRow> };
}

const defaultMacros = { macros: [] };

// The resource's custom macros, its own and those it inherits.
export const getMacrosBlock = ({
  Component,
  getSchema,
  inMassChange = false,
  prefix,
  reservedNames,
  resource: { idPrefix }
}: MacrosSchemaConfig & {
  Component: ComponentType<InputPropsWithoutGroup>;
  // A rule of the resource's own, chained on the shared one.
  getSchema?: (schema: MacrosSchema, context: SectionContext) => MacrosSchema;
  // Off by default: an upsert matches macros by id only, so a name a resource
  // already owns would fail on that resource.
  inMassChange?: boolean;
  resource: ResourceOptions;
}): FieldBlock<MacrosDetail> => {
  const isOffered = ({ isMassChange }: PlatformContext): boolean =>
    inMassChange || !isMassChange;

  return {
    defaultValues: { checkOptions: defaultMacros },
    detailDecoders: {
      checkOptions: JsonDecoder.optional(
        JsonDecoder.nullable(
          JsonDecoder.object(
            {
              macros: JsonDecoder.optional(
                JsonDecoder.array(macroDecoder, 'Macros')
              ).map((value) => value ?? [])
            },
            'Check options'
          )
        )
      ).map((value) => value ?? defaultMacros)
    },
    detailKeyMap: { checkOptions: 'check_options' },
    getInputs: (context) =>
      isOffered(context)
        ? {
            macros: {
              custom: { Component },
              dataTestId: `${idPrefix}-check-options-macros`,
              fieldName: 'checkOptions.macros',
              label: context.t(labelCustomMacros),
              type: InputType.Custom
            }
          }
        : {},
    getSchema: (context): ObjectShape => {
      if (!isOffered(context)) {
        return {};
      }

      const schema = getMacrosSchema({ prefix, reservedNames, t: context.t });

      return {
        checkOptions: object({
          macros: getSchema ? getSchema(schema, context) : schema
        })
      };
    },
    toPayload: (values) => {
      const { checkOptions = defaultMacros } = values as {
        checkOptions?: { macros?: Array<MacroRow> };
      };

      return {
        check_options: { macros: toMacrosPayload(checkOptions.macros) }
      };
    }
  };
};
