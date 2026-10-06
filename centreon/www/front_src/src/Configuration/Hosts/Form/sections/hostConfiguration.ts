import { InputType, type SelectEntry } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

import {
  hostFormPollersEndpoint,
  timezonesEndpoint
} from '../../api/endpoints';
import { namedEntityDecoder } from '../../api/namedEntityDecoders';
import type { NamedEntity } from '../../models';
import {
  labelActiveChecksEnabled,
  labelAlias,
  labelHostConfiguration,
  labelInvalidAddress,
  labelIpAddress,
  labelMaxCheckAttempts,
  labelMonitoringServer,
  labelMustBeIntegerOfAtLeastOne,
  labelName,
  labelNameContainsForbiddenCharacters,
  labelNameMustNotStartWithModule,
  labelNone,
  labelNormalCheckInterval,
  labelPassiveChecksEnabled,
  labelRequired,
  labelResolve,
  labelRetryCheckInterval,
  labelSnmpCommunity,
  labelSnmpVersion,
  labelTimezone
} from '../../translatedLabels';
import ResolveAddress from '../ResolveAddress';
import { buildSelector } from '../selector';
import {
  defaultTriState,
  type TriState,
  triStateDecoder,
  triStateOptions
} from '../triState';
import type { FormSection } from './models';

// Uniqueness is not checked here: the server owns it, and a client check goes
// stale the moment someone else creates a host.
const nameMaxLength = 200;
const aliasMaxLength = 200;
const addressMaxLength = 255;
const snmpCommunityMaxLength = 255;
// The set `CreateHostInput` forbids, character for character. A backslash is
// not among them, so the form must not refuse `C:\temp` either.
const forbiddenNameCharacters = /^[^~!$%^&*"|'<>?,()=]*$/;
// The server spells the reserved prefix `_Module_` or `_Module `.
const moduleNamePrefix = /^_Module[_ ]/;

// An IPv4 or IPv6 address, or a name the poller can resolve. The underscore is
// deliberate: `Assert::HOSTNAME_PATTERN` allows it for NetBIOS and Active
// Directory names, so `srv_01` must reach the API rather than stop here.
const address =
  /^(\d{1,3}(\.\d{1,3}){3}|[\da-fA-F:]+:[\da-fA-F:.]*|\w([\w-]*\w)?(\.\w([\w-]*\w)?)*)$/;

const snmpVersions = ['1', '2c', '3'] as const;

type SnmpVersion = (typeof snmpVersions)[number];

const snmpVersionOptions = snmpVersions.map((version) => ({
  id: version,
  name: version
}));

type SnmpVersionOption = (typeof snmpVersionOptions)[number];

// The select cannot be cleared, so going back to no version is an option of its
// own, as the legacy form's blank entry was. Picking it empties the field.
const noSnmpVersionId = 'none';

// A number field holds `''` until something is typed.
type OptionalNumber = number | '';

interface SchedulingOptionsValues {
  activeCheckEnabled: TriState;
  maxCheckAttempts: OptionalNumber;
  normalCheckInterval: OptionalNumber;
  passiveCheckEnabled: TriState;
  retryCheckInterval: OptionalNumber;
}

interface HostConfigurationDetail {
  address: string;
  alias: string;
  name: string;
  poller: NamedEntity;
  schedulingOptions: SchedulingOptionsValues;
  snmpCommunity: string;
  snmpVersion: SnmpVersionOption | null;
  timezone: NamedEntity | null;
}

const defaultSchedulingOptions: SchedulingOptionsValues = {
  activeCheckEnabled: defaultTriState,
  maxCheckAttempts: '',
  normalCheckInterval: '',
  passiveCheckEnabled: defaultTriState,
  retryCheckInterval: ''
};

// The detail endpoint leaves unset values out rather than sending null.
const optionalNumberDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
).map((value): OptionalNumber => value ?? '');

// Left out on cloud, where the server keeps them unset.
const optionalTriStateDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(triStateDecoder)
).map((value) => value ?? defaultTriState);

// The check period lives in this block of the API too, as `check_period`.
const schedulingOptionsDecoder = JsonDecoder.object<SchedulingOptionsValues>(
  {
    activeCheckEnabled: optionalTriStateDecoder,
    maxCheckAttempts: optionalNumberDecoder,
    normalCheckInterval: optionalNumberDecoder,
    passiveCheckEnabled: optionalTriStateDecoder,
    retryCheckInterval: optionalNumberDecoder
  },
  'Scheduling options',
  {
    activeCheckEnabled: 'active_check_enabled',
    maxCheckAttempts: 'max_check_attempts',
    normalCheckInterval: 'normal_check_interval',
    passiveCheckEnabled: 'passive_check_enabled',
    retryCheckInterval: 'retry_check_interval'
  }
);

const toApiNumber = (value: OptionalNumber | undefined): number | null =>
  value === '' || value === undefined ? null : Number(value);

const getAtLeastOneSchema = (message: string) =>
  number()
    .transform((value, originalValue) => (originalValue === '' ? null : value))
    .nullable()
    .typeError(message)
    .integer(message)
    .min(1, message);

const getSchedulingNumberInput = ({
  fieldName,
  label
}: {
  fieldName: keyof SchedulingOptionsValues;
  label: string;
}) => ({
  dataTestId: `host-form-scheduling-options-${fieldName}`,
  fieldName: `schedulingOptions.${fieldName}`,
  label,
  text: { min: 1, type: 'number' },
  type: InputType.Text
});

const getSchedulingTriStateInput = ({
  fieldName,
  label
}: {
  fieldName: keyof SchedulingOptionsValues;
  label: string;
}) => ({
  dataTestId: `host-form-scheduling-options-${fieldName}`,
  fieldName: `schedulingOptions.${fieldName}`,
  label,
  segmentedButtons: { options: triStateOptions },
  type: InputType.SegmentedButtons
});

export const hostConfiguration: FormSection<HostConfigurationDetail> = {
  defaultValues: {
    address: '',
    alias: '',
    name: '',
    poller: null,
    schedulingOptions: defaultSchedulingOptions,
    snmpCommunity: '',
    snmpVersion: null,
    timezone: null
  },
  detailDecoders: {
    address: JsonDecoder.string,
    // Left out of the response when the host has none.
    alias: JsonDecoder.optional(JsonDecoder.string).map((value) => value ?? ''),
    name: JsonDecoder.string,
    poller: JsonDecoder.object(namedEntityDecoder, 'Poller'),
    schedulingOptions: JsonDecoder.optional(
      JsonDecoder.nullable(schedulingOptionsDecoder)
    ).map((value) => value ?? defaultSchedulingOptions),
    // Write-only: the API never returns it, so the field opens empty.
    snmpCommunity: JsonDecoder.constant(''),
    snmpVersion: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.oneOf(
          snmpVersionOptions.map((option) =>
            JsonDecoder.isExactly<SnmpVersion>(option.id).map(() => option)
          ),
          'SNMP version'
        )
      )
    ).map((value) => value ?? null),
    timezone: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.object(namedEntityDecoder, 'Timezone'))
    ).map((value) => value ?? null)
  },
  detailKeyMap: {
    schedulingOptions: 'scheduling_options',
    snmpCommunity: 'snmp_community',
    snmpVersion: 'snmp_version'
  },
  getInputs: ({ isCloudPlatform, t }) => {
    const checkNumbers = [
      getSchedulingNumberInput({
        fieldName: 'maxCheckAttempts',
        label: t(labelMaxCheckAttempts)
      }),
      getSchedulingNumberInput({
        fieldName: 'normalCheckInterval',
        label: t(labelNormalCheckInterval)
      }),
      getSchedulingNumberInput({
        fieldName: 'retryCheckInterval',
        label: t(labelRetryCheckInterval)
      })
    ];

    return [
      // One row at every panel width, as designed: the fields share it equally
      // and the resolve button keeps its own width.
      {
        fieldName: 'basic-information',
        grid: {
          // The resolve route does not exist on cloud.
          className: isCloudPlatform
            ? 'grid-cols-4'
            : 'grid-cols-[repeat(4,minmax(0,1fr))_auto]',
          columns: [
            {
              dataTestId: 'host-form-name',
              fieldName: 'name',
              label: t(labelName),
              required: true,
              type: InputType.Text
            },
            {
              dataTestId: 'host-form-alias',
              fieldName: 'alias',
              label: t(labelAlias),
              type: InputType.Text
            },
            {
              connectedAutocomplete: buildSelector({
                endpoint: hostFormPollersEndpoint,
                getOptionLabel: (option) => (option as SelectEntry)?.name,
                // The listing filter beside this field carries the same label
                // and reads every poller, where this one reads only the active
                // ones.
                queryKey: 'host-form-poller'
              }),
              dataTestId: 'host-form-poller',
              fieldName: 'poller',
              label: t(labelMonitoringServer),
              required: true,
              type: InputType.SingleConnectedAutocomplete
            },
            {
              dataTestId: 'host-form-address',
              fieldName: 'address',
              label: t(labelIpAddress),
              required: true,
              type: InputType.Text
            },
            ...(isCloudPlatform
              ? []
              : [
                  {
                    custom: { Component: ResolveAddress },
                    dataTestId: 'host-form-address-resolve',
                    fieldName: 'address-resolve',
                    label: t(labelResolve),
                    type: InputType.Custom
                  }
                ])
          ]
        },
        label: 'host-form-basic-information',
        type: InputType.Grid
      },
      // The host's details beside its scheduling once the panel is wide enough,
      // one under the other otherwise.
      {
        fieldName: 'monitoring-layout',
        grid: {
          className: 'grid-cols-1 gap-x-8 @[1100px]:grid-cols-2',
          columns: [
            {
              fieldName: 'host-details',
              grid: {
                className: 'grid-cols-1 @[600px]:grid-cols-3',
                columns: [
                  {
                    dataTestId: 'host-form-snmp-community',
                    fieldName: 'snmpCommunity',
                    label: t(labelSnmpCommunity),
                    type: InputType.Password
                  },
                  {
                    autocomplete: {
                      options: [
                        { id: noSnmpVersionId, name: t(labelNone) },
                        ...snmpVersionOptions
                      ]
                    },
                    change: ({ setFieldValue, value }) =>
                      setFieldValue(
                        'snmpVersion',
                        (value as SelectEntry | null)?.id === noSnmpVersionId
                          ? null
                          : value
                      ),
                    // Not forwarded by the static autocomplete yet: its input
                    // is tested by its label meanwhile.
                    dataTestId: 'host-form-snmp-version',
                    fieldName: 'snmpVersion',
                    label: t(labelSnmpVersion),
                    type: InputType.SingleAutocomplete
                  },
                  {
                    connectedAutocomplete: buildSelector({
                      endpoint: timezonesEndpoint,
                      getOptionLabel: (option) => (option as SelectEntry)?.name,
                      queryKey: 'host-form-timezone'
                    }),
                    dataTestId: 'host-form-timezone',
                    fieldName: 'timezone',
                    label: t(labelTimezone),
                    type: InputType.SingleConnectedAutocomplete
                  }
                ]
              },
              label: 'host-form-host-details',
              type: InputType.Grid
            },
            {
              fieldName: 'scheduling-options',
              grid: {
                // Enabling checks is an onPrem setting; cloud has the numbers
                // alone, side by side.
                className: isCloudPlatform
                  ? 'grid-cols-1 @[600px]:grid-cols-3'
                  : 'grid-cols-1 @[600px]:grid-cols-2',
                columns: isCloudPlatform
                  ? checkNumbers
                  : [
                      {
                        fieldName: 'scheduling-check-numbers',
                        grid: {
                          className: 'grid-cols-1',
                          columns: checkNumbers
                        },
                        label: 'host-form-scheduling-check-numbers',
                        type: InputType.Grid
                      },
                      {
                        fieldName: 'scheduling-checks-enabled',
                        grid: {
                          className: 'grid-cols-1',
                          columns: [
                            getSchedulingTriStateInput({
                              fieldName: 'activeCheckEnabled',
                              label: t(labelActiveChecksEnabled)
                            }),
                            getSchedulingTriStateInput({
                              fieldName: 'passiveCheckEnabled',
                              label: t(labelPassiveChecksEnabled)
                            })
                          ]
                        },
                        label: 'host-form-scheduling-checks-enabled',
                        type: InputType.Grid
                      }
                    ]
              },
              label: 'host-form-scheduling-options',
              type: InputType.Grid
            }
          ]
        },
        label: 'host-form-monitoring-layout',
        type: InputType.Grid
      }
    ];
  },
  getSchema: ({ t }) => ({
    address: string()
      .trim()
      .max(addressMaxLength)
      // Without this an empty address reports itself as invalid rather than
      // as missing, which the name field next to it does not do.
      .matches(address, {
        excludeEmptyString: true,
        message: t(labelInvalidAddress)
      })
      .required(t(labelRequired)),
    alias: string().trim().max(aliasMaxLength),
    // Both fields are trimmed the way the server normalises them, so blanks
    // report as missing instead of passing to a 422.
    name: string()
      .trim()
      .max(nameMaxLength)
      .matches(forbiddenNameCharacters, t(labelNameContainsForbiddenCharacters))
      .test(
        'is-not-a-module',
        t(labelNameMustNotStartWithModule),
        (value) => !moduleNamePrefix.test(value ?? '')
      )
      .required(t(labelRequired)),
    poller: object({
      id: number().required(t(labelRequired)),
      name: string()
    })
      .nullable()
      .required(t(labelRequired)),
    schedulingOptions: object({
      maxCheckAttempts: getAtLeastOneSchema(t(labelMustBeIntegerOfAtLeastOne)),
      normalCheckInterval: getAtLeastOneSchema(
        t(labelMustBeIntegerOfAtLeastOne)
      ),
      retryCheckInterval: getAtLeastOneSchema(t(labelMustBeIntegerOfAtLeastOne))
    }),
    snmpCommunity: string().max(snmpCommunityMaxLength)
  }),
  label: labelHostConfiguration,
  toPayload: (values, { isCloudPlatform }) => {
    const {
      name,
      alias,
      address,
      poller,
      schedulingOptions = defaultSchedulingOptions,
      snmpCommunity,
      snmpVersion,
      timezone
    } = values as {
      address: string;
      alias: string;
      name: string;
      poller: { id: number } | null;
      schedulingOptions?: SchedulingOptionsValues;
      snmpCommunity?: string;
      snmpVersion?: SnmpVersionOption | null;
      timezone?: NamedEntity | null;
    };

    return {
      // Trimmed as the schema validates them: yup casts before checking the
      // length, so an untrimmed value passes `max` here and fails it server
      // side.
      address: address?.trim(),
      // The API has no empty alias: none is null.
      alias: alias?.trim() || null,
      name: name?.trim(),
      poller_id: poller?.id,
      scheduling_options: {
        max_check_attempts: toApiNumber(schedulingOptions.maxCheckAttempts),
        normal_check_interval: toApiNumber(
          schedulingOptions.normalCheckInterval
        ),
        retry_check_interval: toApiNumber(schedulingOptions.retryCheckInterval),
        // Refused on cloud, where the server keeps them unset.
        ...(!isCloudPlatform && {
          active_check_enabled: schedulingOptions.activeCheckEnabled,
          passive_check_enabled: schedulingOptions.passiveCheckEnabled
        })
      },
      // Never read back, so an empty field means "unchanged", not "none": it
      // is left out rather than sent empty.
      ...(snmpCommunity && { snmp_community: snmpCommunity }),
      snmp_version: snmpVersion?.id ?? null,
      timezone_id: timezone?.id ?? null
    };
  }
};
