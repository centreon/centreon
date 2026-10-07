import { InputType, type SelectEntry } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

import {
  commandsEndpoint,
  hostFormPollersEndpoint,
  hostFormTimePeriodsEndpoint,
  timezonesEndpoint
} from '../../api/endpoints';
import { namedEntityDecoder } from '../../api/namedEntityDecoders';
import type { NamedEntity } from '../../models';
import {
  labelActiveChecksEnabled,
  labelAlias,
  labelArgs,
  labelCheckCommand,
  labelCheckPeriod,
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
import { argumentsToText, textToArguments } from '../commandArguments';
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

// Fallback to empty the field: the select cannot be cleared.
const noSnmpVersionId = 'none';

type OptionalNumber = number | '';

interface SchedulingOptionsValues {
  activeCheckEnabled: TriState;
  checkPeriod: NamedEntity | null;
  maxCheckAttempts: OptionalNumber;
  normalCheckInterval: OptionalNumber;
  passiveCheckEnabled: TriState;
  retryCheckInterval: OptionalNumber;
}

// The API's `check_options`, built here alone: the host's macros belong to it
// too.
interface CheckOptionsValues {
  // Typed as legacy shows them, `!arg1!arg2`; the API takes the list.
  args: string;
  command: NamedEntity | null;
}

interface HostConfigurationDetail {
  address: string;
  alias: string;
  checkOptions: CheckOptionsValues;
  name: string;
  poller: NamedEntity;
  schedulingOptions: SchedulingOptionsValues;
  snmpCommunity: string;
  snmpVersion: SnmpVersionOption | null;
  timezone: NamedEntity | null;
}

const defaultSchedulingOptions: SchedulingOptionsValues = {
  activeCheckEnabled: defaultTriState,
  checkPeriod: null,
  maxCheckAttempts: '',
  normalCheckInterval: '',
  passiveCheckEnabled: defaultTriState,
  retryCheckInterval: ''
};

const defaultCheckOptions: CheckOptionsValues = {
  args: '',
  command: null
};

const optionalNumberDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
).map((value): OptionalNumber => value ?? '');

const optionalTriStateDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(triStateDecoder)
).map((value) => value ?? defaultTriState);

const schedulingOptionsDecoder = JsonDecoder.object<SchedulingOptionsValues>(
  {
    activeCheckEnabled: optionalTriStateDecoder,
    checkPeriod: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object(namedEntityDecoder, 'Check period')
      )
    ).map((value) => value ?? null),
    maxCheckAttempts: optionalNumberDecoder,
    normalCheckInterval: optionalNumberDecoder,
    passiveCheckEnabled: optionalTriStateDecoder,
    retryCheckInterval: optionalNumberDecoder
  },
  'Scheduling options',
  {
    activeCheckEnabled: 'active_check_enabled',
    checkPeriod: 'check_period',
    maxCheckAttempts: 'max_check_attempts',
    normalCheckInterval: 'normal_check_interval',
    passiveCheckEnabled: 'passive_check_enabled',
    retryCheckInterval: 'retry_check_interval'
  }
);

const checkOptionsDecoder = JsonDecoder.object<CheckOptionsValues>(
  {
    args: JsonDecoder.optional(
      JsonDecoder.array(JsonDecoder.string, 'Check command arguments')
    ).map((value) => argumentsToText(value ?? [])),
    command: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object(namedEntityDecoder, 'Check command')
      )
    ).map((value) => value ?? null)
  },
  'Check options'
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
    checkOptions: defaultCheckOptions,
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
    checkOptions: JsonDecoder.optional(
      JsonDecoder.nullable(checkOptionsDecoder)
    ).map((value) => value ?? defaultCheckOptions),
    name: JsonDecoder.string,
    poller: JsonDecoder.object(namedEntityDecoder, 'Poller'),
    schedulingOptions: JsonDecoder.optional(
      JsonDecoder.nullable(schedulingOptionsDecoder)
    ).map((value) => value ?? defaultSchedulingOptions),
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
    checkOptions: 'check_options',
    schedulingOptions: 'scheduling_options',
    snmpCommunity: 'snmp_community',
    snmpVersion: 'snmp_version'
  },
  getInputs: ({ isCloudPlatform, t }) => {
    const checkPeriod = {
      connectedAutocomplete: buildSelector({
        endpoint: hostFormTimePeriodsEndpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        queryKey: 'host-form-check-period'
      }),
      dataTestId: 'host-form-scheduling-options-checkPeriod',
      fieldName: 'schedulingOptions.checkPeriod',
      label: t(labelCheckPeriod),
      type: InputType.SingleConnectedAutocomplete
    };

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
      {
        fieldName: 'check-options',
        grid: {
          className: 'grid-cols-1 gap-x-8 @[800px]:grid-cols-2',
          columns: [
            {
              connectedAutocomplete: buildSelector({
                customQueryParameters: [
                  { name: 'type[]', value: 'Check' },
                  { name: 'is_activated', value: true }
                ],
                endpoint: commandsEndpoint,
                getOptionLabel: (option) => (option as SelectEntry)?.name,
                queryKey: 'host-form-check-command'
              }),
              dataTestId: 'host-form-check-options-command',
              fieldName: 'checkOptions.command',
              label: t(labelCheckCommand),
              type: InputType.SingleConnectedAutocomplete
            },
            {
              dataTestId: 'host-form-check-options-args',
              fieldName: 'checkOptions.args',
              // Arguments without a command are refused.
              getDisabled: (values) => !values.checkOptions?.command,
              label: t(labelArgs),
              text: { placeholder: '!arg1!arg2' },
              type: InputType.Text
            }
          ]
        },
        label: 'host-form-check-options',
        type: InputType.Grid
      },
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
                className: 'grid-cols-1 @[600px]:grid-cols-2',
                columns: isCloudPlatform
                  ? [checkPeriod, ...checkNumbers]
                  : [
                      {
                        fieldName: 'scheduling-check-numbers',
                        grid: {
                          className: 'grid-cols-1',
                          columns: [checkPeriod, ...checkNumbers]
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
      checkOptions = defaultCheckOptions,
      poller,
      schedulingOptions = defaultSchedulingOptions,
      snmpCommunity,
      snmpVersion,
      timezone
    } = values as {
      address: string;
      alias: string;
      checkOptions?: CheckOptionsValues;
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
      check_options: {
        // Left over from a command since removed, they would be refused.
        args: checkOptions.command ? textToArguments(checkOptions.args) : [],
        command_id: checkOptions.command?.id ?? null
      },
      name: name?.trim(),
      poller_id: poller?.id,
      scheduling_options: {
        check_timeperiod_id: schedulingOptions.checkPeriod?.id ?? null,
        max_check_attempts: toApiNumber(schedulingOptions.maxCheckAttempts),
        normal_check_interval: toApiNumber(
          schedulingOptions.normalCheckInterval
        ),
        retry_check_interval: toApiNumber(schedulingOptions.retryCheckInterval),
        ...(!isCloudPlatform && {
          active_check_enabled: schedulingOptions.activeCheckEnabled,
          passive_check_enabled: schedulingOptions.passiveCheckEnabled
        })
      },
      // Write-only: empty means unchanged.
      ...(snmpCommunity && { snmp_community: snmpCommunity }),
      snmp_version: snmpVersion?.id ?? null,
      timezone_id: timezone?.id ?? null
    };
  }
};
