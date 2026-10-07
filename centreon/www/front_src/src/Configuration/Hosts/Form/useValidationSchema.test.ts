import type { ValidationError } from 'yup';

import {
  labelInvalidAddress,
  labelMustBeIntegerOfAtLeastOne,
  labelMustBePositiveIntegerOrZero,
  labelNameContainsForbiddenCharacters,
  labelNameMustNotStartWithModule,
  labelParentAndChildHost,
  labelRequired
} from '../translatedLabels';
import useValidationSchema from './useValidationSchema';

// The hook reads nothing but the translator, so it runs as a plain function
// here rather than through a mount: the rules are what is under test.
jest.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (label: string): string => label })
}));

const schemaFor = (isCloudPlatform: boolean) =>
  // biome-ignore lint/correctness/useHookAtTopLevel: with the translator mocked above, it calls no React hook of its own.
  useValidationSchema({ isCloudPlatform }).validationSchema;

const errorFor = (
  field: string,
  value: unknown,
  { isCloudPlatform = false } = {}
): string | null => {
  try {
    schemaFor(isCloudPlatform).validateSyncAt(field, { [field]: value });

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

  describe('Alias', () => {
    it('refuses an alias longer than the 200 characters the API stores', () => {
      expect(errorFor('alias', 'a'.repeat(200))).toBeNull();
      expect(errorFor('alias', 'a'.repeat(201))).not.toBeNull();
    });

    it('measures the alias without its surrounding blanks', () => {
      expect(errorFor('alias', ` ${'a'.repeat(200)} `)).toBeNull();
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

  // `CreateHostInput` counts host groups only under `WhenPlatform(forCloud)`.
  describe('Host groups', () => {
    it('requires at least one on a cloud platform', () => {
      expect(errorFor('groups', [], { isCloudPlatform: true })).toEqual(
        labelRequired
      );
      expect(
        errorFor('groups', [{ id: 1, name: 'Linux servers' }], {
          isCloudPlatform: true
        })
      ).toBeNull();
    });

    it('asks for none anywhere else', () => {
      expect(errorFor('groups', [])).toBeNull();
    });
  });

  describe('Parent and child hosts', () => {
    const relationsError = (
      parentHosts: Array<{ id: number }>,
      childHosts: Array<{ id: number }>
    ): string | null => {
      try {
        schemaFor(false).validateSyncAt('parentHosts', {
          childHosts,
          parentHosts
        });

        return null;
      } catch (error) {
        return (error as ValidationError).message;
      }
    };

    it('refuses a host picked as both parent and child', () => {
      expect(relationsError([{ id: 1 }, { id: 2 }], [{ id: 2 }])).toEqual(
        labelParentAndChildHost
      );
    });

    it('accepts distinct parents and children', () => {
      expect(relationsError([{ id: 1 }], [{ id: 2 }])).toBeNull();
      expect(relationsError([], [])).toBeNull();
    });
  });

  describe('Notification delays', () => {
    const delayError = (field: string, value: unknown): string | null => {
      try {
        schemaFor(false).validateSyncAt(`notifications.${field}`, {
          notifications: { [field]: value }
        });

        return null;
      } catch (error) {
        return (error as ValidationError).message;
      }
    };

    it.each(['interval', 'firstDelay', 'recoveryDelay'])(
      'accepts an empty %s, 0 and a positive integer',
      (field) => {
        expect(delayError(field, '')).toBeNull();
        expect(delayError(field, 0)).toBeNull();
        expect(delayError(field, 12)).toBeNull();
      }
    );

    it.each(['interval', 'firstDelay', 'recoveryDelay'])(
      'refuses a negative or fractional %s',
      (field) => {
        expect(delayError(field, -1)).toEqual(labelMustBePositiveIntegerOrZero);
        expect(delayError(field, 1.5)).toEqual(
          labelMustBePositiveIntegerOrZero
        );
      }
    );
  });

  describe('Scheduling options', () => {
    const schedulingError = (field: string, value: unknown): string | null => {
      try {
        schemaFor(false).validateSyncAt(`schedulingOptions.${field}`, {
          schedulingOptions: { [field]: value }
        });

        return null;
      } catch (error) {
        return (error as ValidationError).message;
      }
    };

    const fields = [
      'maxCheckAttempts',
      'normalCheckInterval',
      'retryCheckInterval'
    ];

    it.each(fields)('accepts an empty %s and a positive integer', (field) => {
      expect(schedulingError(field, '')).toBeNull();
      expect(schedulingError(field, 1)).toBeNull();
      expect(schedulingError(field, 12)).toBeNull();
    });

    it.each(fields)('refuses a %s of 0, negative or fractional', (field) => {
      expect(schedulingError(field, 0)).toEqual(labelMustBeIntegerOfAtLeastOne);
      expect(schedulingError(field, -1)).toEqual(
        labelMustBeIntegerOfAtLeastOne
      );
      expect(schedulingError(field, 1.5)).toEqual(
        labelMustBeIntegerOfAtLeastOne
      );
    });
  });

  describe('Data processing', () => {
    const dataProcessingError = (
      field: string,
      value: unknown
    ): string | null => {
      try {
        schemaFor(false).validateSyncAt(`dataProcessing.${field}`, {
          dataProcessing: { [field]: value }
        });

        return null;
      } catch (error) {
        return (error as ValidationError).message;
      }
    };

    it('accepts a freshness threshold of 0, which leaves it to the engine', () => {
      expect(dataProcessingError('freshnessThreshold', '')).toBeNull();
      expect(dataProcessingError('freshnessThreshold', 0)).toBeNull();
      expect(dataProcessingError('freshnessThreshold', -1)).toEqual(
        labelMustBePositiveIntegerOrZero
      );
    });

    it('refuses an acknowledgement timeout below 1', () => {
      expect(dataProcessingError('acknowledgmentTimeout', 1)).toBeNull();
      expect(dataProcessingError('acknowledgmentTimeout', 0)).toEqual(
        labelMustBeIntegerOfAtLeastOne
      );
    });

    it.each(['lowFlapThreshold', 'highFlapThreshold'])(
      'accepts a %s of 0 or more, leaving the 100 cap to the server',
      (field) => {
        expect(dataProcessingError(field, '')).toBeNull();
        expect(dataProcessingError(field, 0)).toBeNull();
        expect(dataProcessingError(field, 101)).toBeNull();
        expect(dataProcessingError(field, -1)).toEqual(
          labelMustBePositiveIntegerOrZero
        );
        expect(dataProcessingError(field, 12.5)).toEqual(
          labelMustBePositiveIntegerOrZero
        );
      }
    );
  });

  describe('SNMP community', () => {
    it('accepts up to 255 characters', () => {
      expect(errorFor('snmpCommunity', '')).toBeNull();
      expect(errorFor('snmpCommunity', 'a'.repeat(255))).toBeNull();
      expect(errorFor('snmpCommunity', 'a'.repeat(256))).not.toBeNull();
    });
  });
});
