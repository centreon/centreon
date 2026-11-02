import type { TFunction } from 'i18next';
import { array, object, string } from 'yup';

import {
  labelAlreadyExists,
  labelMustBeAtMostCharacters,
  labelName,
  labelRequired,
  labelReservedMacroName,
  labelValue
} from '../../translatedLabels';
import { toMacroName, toStoredMacroName } from './macroRows';
import type { MacrosSchemaConfig } from './models';

// The server stores the name, once wrapped in `<prefix>…$`, in 255 characters.
const storedNameMaxLength = 255;
const valueMaxLength = 4096;

// 248 for `$_HOST`, 245 for `$_SERVICE`.
export const getMacroNameMaxLength = (prefix: string): number =>
  storedNameMaxLength - prefix.length - '$'.length;

// The rules on a list of macros (`MacroRow`). Duplicate names are refused,
// case-insensitively, as the server upper-cases them: the hosts' server
// silently keeps the first of two on creation, the services' refuses them.
export const getMacrosSchema = ({
  prefix,
  reservedNames = [],
  t
}: MacrosSchemaConfig & { t: TFunction }) => {
  const nameMaxLength = getMacroNameMaxLength(prefix);

  return array()
    .of(
      object({
        name: string()
          .trim()
          .max(
            nameMaxLength,
            t(labelMustBeAtMostCharacters, {
              label: t(labelName),
              max: nameMaxLength
            })
          )
          .required(t(labelRequired))
          .test(
            'not-reserved',
            t(labelReservedMacroName),
            (name) =>
              !name ||
              !reservedNames.includes(toStoredMacroName({ name, prefix }))
          ),
        value: string().max(
          valueMaxLength,
          t(labelMustBeAtMostCharacters, {
            label: t(labelValue),
            max: valueMaxLength
          })
        )
      })
    )
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
};
