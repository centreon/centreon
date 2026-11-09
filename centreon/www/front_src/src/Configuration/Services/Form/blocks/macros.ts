import { InputType } from '@centreon/ui';

import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { type ObjectShape, object } from 'yup';

import {
  getMacrosSchema,
  isUnchangedInherited,
  type MacroRow,
  macroDecoder,
  toMacrosPayload
} from '../../../ConfigurationBase/Form/Macros';
import type { FieldBlock, ResourceOptions } from '../../../ResourceForm/models';
import { labelRequired } from '../../../ResourceForm/translatedLabels';
import { labelCustomMacros } from '../../translatedLabels';
import ServiceMacros from '../ServiceMacros';

// `ServiceMacroInput`: the name once wrapped in `$_SERVICE…$` fits 255
// characters, and the criticality macros belong to the server.
const macroPrefix = '$_SERVICE';
const reservedMacroNames = [
  '$_SERVICECRITICALITY_LEVEL$',
  '$_SERVICECRITICALITY_ID$'
];

// Within the API's `check_options`, which the check command fills too.
interface MacrosDetail {
  checkOptions: { macros: Array<MacroRow> };
}

const defaultMacros = { macros: [] };

// A value, unless the row is still what the service inherits (not sent) or a
// stored password left as is (sent as null, the server keeps it).
const isMissingValue = (macro: MacroRow): boolean =>
  !isUnchangedInherited(macro) &&
  !(macro.hasStoredPassword && !macro.isErased) &&
  (macro.value ?? '') === '';

const getServiceMacrosSchema = (t: TFunction) =>
  getMacrosSchema({
    prefix: macroPrefix,
    reservedNames: reservedMacroNames,
    t
  }).test('values-required', function checkValuesRequired(macros) {
    const index = ((macros ?? []) as Array<MacroRow>).findIndex(isMissingValue);

    return (
      index === -1 ||
      this.createError({
        message: t(labelRequired),
        path: `${this.path}[${index}].value`
      })
    );
  });

// The service's custom macros, its own and those it inherits from its
// template and its command.
export const getMacrosBlock = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<MacrosDetail> => ({
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
  // Not in a mass change, as on hosts: an upsert matches macros by id only.
  getInputs: ({ isMassChange, t }) =>
    isMassChange
      ? {}
      : {
          macros: {
            custom: { Component: ServiceMacros },
            dataTestId: `${idPrefix}-check-options-macros`,
            fieldName: 'checkOptions.macros',
            label: t(labelCustomMacros),
            type: InputType.Custom
          }
        },
  getSchema: ({ isMassChange, t }): ObjectShape =>
    isMassChange
      ? {}
      : { checkOptions: object({ macros: getServiceMacrosSchema(t) }) },
  toPayload: (values) => {
    const { checkOptions = defaultMacros } = values as {
      checkOptions?: { macros?: Array<MacroRow> };
    };

    return { check_options: { macros: toMacrosPayload(checkOptions.macros) } };
  }
});
