import type { TFunction } from 'i18next';
import type { ValidationError } from 'yup';

import {
  labelAlreadyExists,
  labelMustBeAtMostCharacters,
  labelReservedMacroName
} from '../../translatedLabels';
import { createMacroRow, toMacrosPayload } from './macroRows';
import { getMacroNameMaxLength, getMacrosSchema } from './macrosSchema';

const t = ((label: string): string => label) as unknown as TFunction;

const macro = (name: string, rest = {}) => ({
  ...createMacroRow(),
  name,
  ...rest
});

const servicesSchema = getMacrosSchema({
  prefix: '$_SERVICE',
  reservedNames: ['$_SERVICECRITICALITY_LEVEL$', '$_SERVICECRITICALITY_ID$'],
  t
});

const errorFor = (macros: Array<object>): string | null => {
  try {
    servicesSchema.validateSync(macros);

    return null;
  } catch (error) {
    return (error as ValidationError).message;
  }
};

describe('getMacroNameMaxLength', () => {
  it('leaves room for the prefix and the closing dollar in 255 characters', () => {
    expect(getMacroNameMaxLength('$_HOST')).toEqual(248);
    expect(getMacroNameMaxLength('$_SERVICE')).toEqual(245);
  });
});

describe('getMacrosSchema', () => {
  it('refuses a reserved name, whatever its case', () => {
    expect(errorFor([macro(' criticality_level ')])).toEqual(
      labelReservedMacroName
    );
    expect(errorFor([macro('CRITICALITY_ID')])).toEqual(labelReservedMacroName);
    expect(errorFor([macro('CRITICALITY')])).toBeNull();
  });

  it('accepts reserved names when none are given', () => {
    expect(
      getMacrosSchema({ prefix: '$_SERVICE', t }).isValidSync([
        macro('CRITICALITY_LEVEL')
      ])
    ).toBe(true);
  });

  it('sizes the name after the prefix', () => {
    expect(errorFor([macro('a'.repeat(245))])).toBeNull();
    expect(errorFor([macro('a'.repeat(246))])).toEqual(
      labelMustBeAtMostCharacters
    );
  });

  it('refuses duplicate names case-insensitively', () => {
    expect(errorFor([macro('port'), macro(' PORT ')])).toEqual(
      labelAlreadyExists
    );
  });
});

describe('toMacrosPayload', () => {
  it('leaves unchanged inherited macros out and keeps a stored password', () => {
    const inherited = {
      id: 3,
      inherited: { isPassword: false, name: 'OS', value: 'linux' },
      parent: 'template' as const,
      value: 'linux'
    };

    expect(
      toMacrosPayload([
        macro('OS', inherited),
        macro('OS', { ...inherited, value: 'debian' }),
        macro(' token ', { hasStoredPassword: true, id: 7, isPassword: true })
      ])
    ).toEqual([
      {
        id: 3,
        is_password: false,
        name: 'OS',
        parent: 'template',
        value: 'debian'
      },
      { id: 7, is_password: true, name: 'TOKEN', parent: null, value: null }
    ]);
  });
});
