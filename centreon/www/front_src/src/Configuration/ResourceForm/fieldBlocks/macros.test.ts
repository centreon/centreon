import type { TFunction } from 'i18next';
import { object, type ValidationError } from 'yup';

import { createMacroRow } from '../../ConfigurationBase/Form/Macros';
import {
  labelMustBeAtMostCharacters,
  labelReservedMacroName
} from '../../ConfigurationBase/translatedLabels';
import { getDefaultValues, getInputBlocks, getPayload } from '../blocks';
import type { SectionContext } from '../models';
import { getMacrosBlock } from './macros';

const t = ((label: string) => label) as TFunction;
const context: SectionContext = { isCloudPlatform: false, t };
const massChange: SectionContext = { ...context, isMassChange: true };

const Component = () => null;
const resource = { baseEndpoint: '/hosts', idPrefix: 'form' };

const getBlockOf = (
  options: Partial<Parameters<typeof getMacrosBlock>[0]> = {}
) => getMacrosBlock({ Component, prefix: '$_HOST', resource, ...options });

const macro = (name: string, rest = {}) => ({
  ...createMacroRow(),
  name,
  ...rest
});

const errorFor = (
  block: ReturnType<typeof getBlockOf>,
  macros: Array<object>
): string | null => {
  try {
    object(block.getSchema(context)).validateSync({
      checkOptions: { macros }
    });

    return null;
  } catch (error) {
    return (error as ValidationError).message;
  }
};

describe('getMacrosBlock', () => {
  it('hands over the macros of the resource, read from and sent in its check options', () => {
    const block = getBlockOf();

    expect(getInputBlocks([block], context).macros).toMatchObject({
      custom: { Component },
      dataTestId: 'form-check-options-macros',
      fieldName: 'checkOptions.macros'
    });
    expect(getDefaultValues([block], context)).toEqual({
      checkOptions: { macros: [] }
    });
    expect(
      getPayload(
        [block],
        { checkOptions: { macros: [macro('port')] } },
        context
      )
    ).toEqual({
      check_options: {
        macros: [
          {
            id: null,
            is_password: false,
            name: 'PORT',
            parent: null,
            value: ''
          }
        ]
      }
    });
  });

  it('fits the name in 255 characters once wrapped in its prefix', () => {
    const name = 'a'.repeat(246);

    expect(errorFor(getBlockOf(), [macro(name)])).toBeNull();
    expect(errorFor(getBlockOf({ prefix: '$_SERVICE' }), [macro(name)])).toBe(
      labelMustBeAtMostCharacters
    );
  });

  it('refuses the reserved names it is given, and only those', () => {
    const reserved = getBlockOf({
      prefix: '$_SERVICE',
      reservedNames: ['$_SERVICECRITICALITY_LEVEL$']
    });

    expect(errorFor(reserved, [macro('criticality_level')])).toBe(
      labelReservedMacroName
    );
    expect(errorFor(reserved, [macro('criticality_id')])).toBeNull();
    expect(
      errorFor(getBlockOf({ prefix: '$_SERVICE' }), [
        macro('criticality_level')
      ])
    ).toBeNull();
  });

  it('applies the resource rule on top of the shared one', () => {
    const block = getBlockOf({
      getSchema: (schema) =>
        schema.test(
          'at-most-one',
          'one macro at most',
          (macros) => (macros ?? []).length <= 1
        )
    });

    expect(errorFor(block, [macro('a'.repeat(249))])).toBe(
      labelMustBeAtMostCharacters
    );
    expect(errorFor(block, [macro('port'), macro('timeout')])).toBe(
      'one macro at most'
    );
    expect(errorFor(block, [macro('port')])).toBeNull();
  });

  it('is left out of a mass change unless offered there', () => {
    const block = getBlockOf();

    expect(getInputBlocks([block], massChange)).toEqual({});
    expect(block.getSchema(massChange)).toEqual({});

    const offered = getBlockOf({ inMassChange: true });

    expect(getInputBlocks([offered], massChange).macros).toBeDefined();
    expect(offered.getSchema(massChange)).toHaveProperty('checkOptions');
  });
});
