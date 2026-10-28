import { InputType } from '@centreon/ui';

import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { array, object, string } from 'yup';

import type { FieldBlock, ResourceOptions } from '../../../ResourceForm/models';
import {
  labelAlreadyExists,
  labelCustomMacros,
  labelMustBeAtMostCharacters,
  labelName,
  labelRequired,
  labelValue
} from '../../translatedLabels';
import Macros from '../Macros';
import {
  hostMacroDecoder,
  isUnchangedInherited,
  type MacroRow,
  toMacroName
} from '../macroRows';

// `HostMacroInput`: the name once wrapped in `$_HOST…$` fits 255 characters,
// the value 4096 characters.
const macroNameMaxLength = 248;
const macroValueMaxLength = 4096;

// Within the API's `check_options`, which the check command fills too.
interface MacrosDetail {
  checkOptions: { macros: Array<MacroRow> };
}

const defaultMacros = { macros: [] };

// A changed inherited macro goes back with its id and parent, so the server
// turns it into the host's own.
const toMacroPayload = ({
  hasStoredPassword,
  id,
  isErased,
  isPassword,
  name,
  parent,
  value
}: MacroRow) => ({
  id,
  is_password: isPassword,
  name: toMacroName(name),
  parent,
  // Null keeps a stored password; once cleared, what is typed replaces it,
  // empty included.
  value: hasStoredPassword && !isErased ? null : value
});

const getMacrosSchema = (t: TFunction) =>
  array()
    .of(
      object({
        name: string()
          .trim()
          .max(
            macroNameMaxLength,
            t(labelMustBeAtMostCharacters, {
              label: t(labelName),
              max: macroNameMaxLength
            })
          )
          .required(t(labelRequired)),
        value: string().max(
          macroValueMaxLength,
          t(labelMustBeAtMostCharacters, {
            label: t(labelValue),
            max: macroValueMaxLength
          })
        )
      })
    )
    // The server silently keeps the first of two macros of the same name.
    // Reserved names are left to it.
    .test('unique-names', function checkUniqueNames(macros) {
      const names = (macros ?? []).map(({ name }) => toMacroName(name ?? ''));
      const duplicateIndex = names.findIndex(
        (name, index) => name !== '' && names.indexOf(name) !== index
      );

      return (
        duplicateIndex === -1 ||
        this.createError({
          message: t(labelAlreadyExists),
          path: `${this.path}[${duplicateIndex}].name`
        })
      );
    });

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
              JsonDecoder.array(hostMacroDecoder, 'Macros')
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
            custom: { Component: Macros },
            dataTestId: `${idPrefix}-check-options-macros`,
            fieldName: 'checkOptions.macros',
            label: t(labelCustomMacros),
            type: InputType.Custom
          }
        },
  getSchema: ({ t }) => ({
    checkOptions: object({ macros: getMacrosSchema(t) })
  }),
  toPayload: (values) => {
    const { checkOptions = defaultMacros } = values as {
      checkOptions?: { macros?: Array<MacroRow> };
    };

    return {
      check_options: {
        // Unchanged inherited macros stay inherited. Not sent, they cannot go
        // stale once the templates or the command change.
        macros: (checkOptions.macros ?? [])
          .filter((macro) => !isUnchangedInherited(macro))
          .map(toMacroPayload)
      }
    };
  }
});
