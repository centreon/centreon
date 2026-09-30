import type { ValidationError } from 'yup';

import {
  labelInvalidAddress,
  labelNameContainsForbiddenCharacters,
  labelNameMustNotStartWithModule,
  labelRequired
} from '../translatedLabels';
import useValidationSchema from './useValidationSchema';

// The hook reads nothing but the translator, so it runs as a plain function
// here rather than through a mount: the rules are what is under test.
jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (label: string): string => label })
}));

// biome-ignore lint/correctness/useHookAtTopLevel: with the translator mocked above, it calls no React hook of its own.
const { validationSchema } = useValidationSchema();

const errorFor = (field: string, value: unknown): string | null => {
  try {
    validationSchema.validateSyncAt(field, { [field]: value });

    return null;
  } catch (error) {
    return (error as ValidationError).message;
  }
};

describe('Host form validation', () => {
  describe('Name', () => {
    it.each([
      ['a plain name', 'srv-apache-02', null],
      // The server's forbidden set has no backslash, so neither has the form.
      ['a Windows path', 'C:\\temp', null],
      [
        'a character the API rejects',
        'srv<apache>',
        labelNameContainsForbiddenCharacters
      ],
      ['the reserved prefix', '_Module_BAM', labelNameMustNotStartWithModule],
      // The server spells the prefix with a space too.
      [
        'the reserved prefix, spaced',
        '_Module BAM',
        labelNameMustNotStartWithModule
      ],
      ['blanks only', '   ', labelRequired],
      ['nothing at all', '', labelRequired]
    ])('reports %s', (_, value, expected) => {
      expect(errorFor('name', value)).toEqual(expected);
    });

    it('refuses a name longer than the 200 characters the API stores', () => {
      expect(errorFor('name', 'a'.repeat(201))).not.toBeNull();
      expect(errorFor('name', 'a'.repeat(200))).toBeNull();
    });
  });

  describe('Address', () => {
    it.each([
      ['an IPv4 address', '10.0.0.42', null],
      ['an IPv6 address', 'fe80::1', null],
      ['a fully qualified name', 'srv.example.com', null],
      // `Assert::HOSTNAME_PATTERN` allows the underscore for NetBIOS and
      // Active Directory names; refusing it here would strand those hosts.
      ['an underscored name', 'srv_01.example.com', null],
      ['a short underscored name', 'my_host', null],
      ['a name with spaces', 'not a host!', labelInvalidAddress],
      // Missing, not invalid: the two mandatory text fields agree on this.
      ['nothing at all', '', labelRequired],
      ['blanks only', '   ', labelRequired]
    ])('reports %s', (_, value, expected) => {
      expect(errorFor('address', value)).toEqual(expected);
    });

    it('refuses an address longer than the 255 characters the API stores', () => {
      expect(errorFor('address', `${'a'.repeat(256)}`)).not.toBeNull();
    });
  });

  describe('Monitoring server', () => {
    it('requires one', () => {
      expect(errorFor('poller', null)).toEqual(labelRequired);
      expect(errorFor('poller', { id: 2, name: 'Poller EU' })).toBeNull();
    });
  });
});
