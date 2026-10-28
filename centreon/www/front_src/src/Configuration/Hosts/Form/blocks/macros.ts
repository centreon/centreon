import { InputType } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { object } from 'yup';

import {
  getMacrosSchema,
  type MacroRow,
  macroDecoder,
  toMacrosPayload
} from '../../../ConfigurationBase/Form/Macros';
import type { FieldBlock, ResourceOptions } from '../../../ResourceForm/models';
import { labelCustomMacros } from '../../translatedLabels';
import HostMacros from '../HostMacros';

// `HostMacroInput`: the name once wrapped in `$_HOST…$` fits 255 characters.
// Reserved names are left to the server.
const macroPrefix = '$_HOST';

// Within the API's `check_options`, which the check command fills too.
interface MacrosDetail {
  checkOptions: { macros: Array<MacroRow> };
}

const defaultMacros = { macros: [] };

// The host's custom macros, its own and those it inherits.
export const macros = ({
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
  // Not in a mass change yet: an upsert matches macros by id only, so a name a
  // host already owns would fail on that host.
  getInputs: ({ isMassChange, t }) =>
    isMassChange
      ? {}
      : {
          macros: {
            custom: { Component: HostMacros },
            dataTestId: `${idPrefix}-check-options-macros`,
            fieldName: 'checkOptions.macros',
            label: t(labelCustomMacros),
            type: InputType.Custom
          }
        },
  getSchema: ({ t }) => ({
    checkOptions: object({
      macros: getMacrosSchema({ prefix: macroPrefix, t })
    })
  }),
  toPayload: (values) => {
    const { checkOptions = defaultMacros } = values as {
      checkOptions?: { macros?: Array<MacroRow> };
    };

    return { check_options: { macros: toMacrosPayload(checkOptions.macros) } };
  }
});
