import type { ValidationError } from 'yup';

import {
  labelMustBeAtMostCharacters,
  labelRequired
} from '../../ResourceForm/translatedLabels';
import { labelNameContainsForbiddenCharacters } from '../translatedLabels';
import useValidationSchema from './useValidationSchema';

// The hook reads nothing but the translator, so it runs as a plain function
// here rather than through a mount: the rules are what is under test.
jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (label: string): string => label })
}));

const errorFor = (field: string, value: unknown): string | null => {
  try {
    // biome-ignore lint/correctness/useHookAtTopLevel: with the translator mocked above, it calls no React hook of its own.
    useValidationSchema({
      isCloudPlatform: false
    }).validationSchema.validateSyncAt(field, { [field]: value });

    return null;
  } catch (error) {
    return (error as ValidationError).message;
  }
};

describe('Service form validation', () => {
  describe('Name', () => {
    it.each([
      ['a plain name', 'cpu load', null],
      // The server's forbidden set has no backslash, so neither has the form.
      ['a Windows path', 'C:\\temp', null],
      ['a tab inside', 'cpu\tload', labelNameContainsForbiddenCharacters],
      [
        'a line break inside',
        'cpu\nload',
        labelNameContainsForbiddenCharacters
      ],
      [
        'a delete character',
        'cpu\u007fload',
        labelNameContainsForbiddenCharacters
      ],
      // Trimmed away before it is checked, and before it is sent.
      ['a line break at the end', 'cpu\n', null],
      ['200 characters', 'a'.repeat(200), null],
      ['201 characters', 'a'.repeat(201), labelMustBeAtMostCharacters],
      ['200 characters and blanks around', `  ${'a'.repeat(200)}  `, null],
      // The server collapses the double space, and stores 200 characters.
      [
        '201 characters with a double space',
        `${'a'.repeat(100)}  ${'a'.repeat(99)}`,
        null
      ],
      ['blanks only', '   ', labelRequired],
      ['nothing at all', '', labelRequired]
    ])('%s', (_case, value, expected) => {
      expect(errorFor('name', value)).toBe(expected);
    });

    it.each([
      '~',
      '!',
      '$',
      '%',
      '^',
      '&',
      '*',
      '"',
      '|',
      "'",
      '<',
      '>',
      '?',
      ',',
      '(',
      ')',
      '='
    ])('refuses %s', (character) => {
      expect(errorFor('name', `cpu${character}load`)).toBe(
        labelNameContainsForbiddenCharacters
      );
    });
  });

  describe('Host', () => {
    it('asks for one', () => {
      expect(errorFor('host', null)).toBe(labelRequired);
    });

    it('takes a picked host', () => {
      expect(errorFor('host', { id: 10, name: 'host A' })).toBeNull();
    });
  });
});
