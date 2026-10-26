import {
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

import {
  getMacrosSchema,
  type MacroRow,
  macroDecoder,
  toMacrosPayload
} from '../../../ConfigurationBase/Form/Macros';
import {
  hostFormCommandsEndpoint,
  hostFormPollersEndpoint,
  hostFormTimePeriodsEndpoint,
  timezonesEndpoint
} from '../../api/endpoints';
import { namedEntityDecoder } from '../../api/namedEntityDecoders';
import type { NamedEntity } from '../../models';
import {
  labelActiveChecksEnabled,
  labelAddress,
  labelAlias,
  labelArgs,
  labelArguments,
  labelCheckCommand,
  labelCheckPeriod,
  labelCreateServicesLinkedToTemplates,
  labelCustomMacros,
  labelHostBasicInformation,
  labelHostCheckOptions,
  labelInvalidAddress,
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
  labelTemplates,
  labelTimezone
} from '../../translatedLabels';
import { argumentsToText, textToArguments } from '../commandArguments';
import { getBlock, twoColumns } from '../grid';
import HostMacros from '../HostMacros';
import ResolveAddress from '../ResolveAddress';
import { buildSelector, toIds } from '../selector';
import Templates, { type TemplateRow } from '../Templates';
import {
  defaultTriState,
  type TriState,
  triStateDecoder,
  triStateOptions
} from '../triState';
import { withUpdateMode } from '../updateMode';
import type { FormSection } from './models';

// Uniqueness is not checked here: the server owns it, and a client check goes
// stale the moment someone else creates a host.
const nameMaxLength = 200;
const aliasMaxLength = 200;
const addressMaxLength = 255;
const snmpCommunityMaxLength = 255;
// `HostMacroInput`: the name once wrapped in `$_HOST…$` fits 255 characters.
// Reserved names are left to the server.
const macroPrefix = '$_HOST';
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
  args: string;
  command: NamedEntity | null;
  macros: Array<MacroRow>;
}

interface HostConfigurationDetail {
  address: string;
  alias: string;
  checkOptions: CheckOptionsValues;
  createServicesLinkedToTemplates: boolean;
  name: string;
  poller: NamedEntity;
  schedulingOptions: SchedulingOptionsValues;
  snmpCommunity: string;
  snmpVersion: SnmpVersionOption | null;
  templates: Array<TemplateRow>;
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
  command: null,
  macros: []
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
    ).map((value) => value ?? null),
    macros: JsonDecoder.optional(JsonDecoder.array(macroDecoder, 'Macros')).map(
      (value) => value ?? []
    )
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

// The rules on one host's own fields, and the required poller. A mass change
// shows none of the former and leaves the poller optional.
const getHostOwnSchema = (t: TFunction) => ({
  address: string()
    .trim()
    .max(addressMaxLength)
    // Without this an empty address reports itself as invalid rather than as
    // missing, which the name field next to it does not do.
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
    .required(t(labelRequired))
});

export const hostConfiguration: FormSection<HostConfigurationDetail> = {
  defaultValues: {
    address: '',
    alias: '',
    checkOptions: defaultCheckOptions,
    // As legacy creates a host: its templates' services along with it.
    createServicesLinkedToTemplates: true,
    name: '',
    poller: null,
    schedulingOptions: defaultSchedulingOptions,
    snmpCommunity: '',
    snmpVersion: null,
    templates: [],
    timezone: null
  },
  detailDecoders: {
    address: JsonDecoder.string,
    // Left out of the response when the host has none.
    alias: JsonDecoder.optional(JsonDecoder.string).map((value) => value ?? ''),
    checkOptions: JsonDecoder.optional(
      JsonDecoder.nullable(checkOptionsDecoder)
    ).map((value) => value ?? defaultCheckOptions),
    // Never read back. Off, as legacy opens an existing host: saving it does
    // not create its templates' services again unless asked to.
    createServicesLinkedToTemplates: JsonDecoder.constant(false),
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
    // In the order they are inherited from.
    templates: JsonDecoder.optional(
      JsonDecoder.array<TemplateRow>(
        JsonDecoder.object(namedEntityDecoder, 'Template'),
        'Templates'
      )
    ).map((value) => value ?? []),
    timezone: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.object(namedEntityDecoder, 'Timezone'))
    ).map((value) => value ?? null)
  },
  detailKeyMap: {
    checkOptions: 'check_options',
    createServicesLinkedToTemplates: 'create_services_linked_to_templates',
    schedulingOptions: 'scheduling_options',
    snmpCommunity: 'snmp_community',
    snmpVersion: 'snmp_version'
  },
  getInputs: ({ isCloudPlatform, isMassChange, t }) => {
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

    const poller = {
      connectedAutocomplete: buildSelector({
        endpoint: hostFormPollersEndpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        // The listing filter beside this field carries the same label and
        // reads every poller, where this one reads only the active ones.
        queryKey: 'host-form-poller'
      }),
      dataTestId: 'host-form-poller',
      fieldName: 'poller',
      label: t(labelMonitoringServer),
      required: !isMassChange,
      type: InputType.SingleConnectedAutocomplete
    };

    const snmpCommunity = {
      dataTestId: 'host-form-snmp-community',
      fieldName: 'snmpCommunity',
      label: t(labelSnmpCommunity),
      type: InputType.Password
    };

    const snmpVersion: InputPropsWithoutGroup = {
      autocomplete: {
        options: [
          { id: noSnmpVersionId, name: t(labelNone) },
          ...snmpVersionOptions
        ]
      },
      change: ({ setFieldValue, value }) =>
        setFieldValue(
          'snmpVersion',
          (value as SelectEntry | null)?.id === noSnmpVersionId ? null : value
        ),
      dataTestId: 'host-form-snmp-version',
      fieldName: 'snmpVersion',
      label: t(labelSnmpVersion),
      type: InputType.SingleAutocomplete
    };

    return {
      // One row at every panel width, as designed: the fields share it equally
      // and the resolve button keeps its own width.
      // Name, alias and address belong to one host: legacy mass change has
      // none.
      ...(!isMassChange && {
        basicInformation: getBlock({
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
            poller,
            {
              dataTestId: 'host-form-address',
              fieldName: 'address',
              label: t(labelAddress),
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
          ],
          name: 'basic-information',
          title: t(labelHostBasicInformation)
        })
      }),
      checkCommand: getBlock({
        className: twoColumns,
        columns: [
          {
            additionalLabel: t(labelHostCheckOptions),
            connectedAutocomplete: buildSelector({
              customQueryParameters: [
                { name: 'type[]', value: 'Check' },
                { name: 'is_activated', value: true }
              ],
              endpoint: hostFormCommandsEndpoint,
              getOptionLabel: (option) => (option as SelectEntry)?.name,
              queryKey: 'host-form-check-command'
            }),
            dataTestId: 'host-form-check-options-command',
            fieldName: 'checkOptions.command',
            label: t(labelCheckCommand),
            type: InputType.SingleConnectedAutocomplete
          },
          {
            additionalLabel: t(labelArguments),
            dataTestId: 'host-form-check-options-args',
            fieldName: 'checkOptions.args',
            // Arguments without a command are refused.
            getDisabled: (values) => !values.checkOptions?.command,
            label: t(labelArgs),
            text: { placeholder: '!arg1!arg2' },
            type: InputType.Text
          }
        ],
        name: 'check-options'
      }),
      // Not in a mass change yet: an upsert matches macros by id only, so a
      // name a host already owns would fail on that host.
      ...(!isMassChange && {
        macros: {
          custom: { Component: HostMacros },
          dataTestId: 'host-form-check-options-macros',
          fieldName: 'checkOptions.macros',
          label: t(labelCustomMacros),
          type: InputType.Custom
        }
      }),
      // The selector the basic information holds for a single host.
      ...(isMassChange && { massChangePoller: poller }),
      ...(!isCloudPlatform && {
        schedulingChecks: getBlock({
          columns: [
            getSchedulingTriStateInput({
              fieldName: 'activeCheckEnabled',
              label: t(labelActiveChecksEnabled)
            }),
            getSchedulingTriStateInput({
              fieldName: 'passiveCheckEnabled',
              label: t(labelPassiveChecksEnabled)
            })
          ],
          name: 'scheduling-checks-enabled'
        })
      }),
      schedulingNumbers: getBlock({
        columns: [checkPeriod, ...checkNumbers],
        name: 'scheduling-check-numbers'
      }),
      // Not in the design: placed right after the check command on request.
      // Password managers take the text field before a password for its user
      // name, here the check command arguments.
      snmp: getBlock({
        className: twoColumns,
        columns: [snmpCommunity, snmpVersion],
        name: 'snmp'
      }),
      templates: getBlock({
        columns: [
          withUpdateMode({
            field: 'templates',
            input: {
              custom: { Component: Templates },
              dataTestId: 'host-form-templates',
              fieldName: 'templates',
              label: t(labelTemplates),
              type: InputType.Custom
            },
            isMassChange,
            t
          }),
          // Cloud always creates them.
          ...(isCloudPlatform
            ? []
            : [
                {
                  dataTestId: 'host-form-create-services-linked-to-templates',
                  fieldName: 'createServicesLinkedToTemplates',
                  label: t(labelCreateServicesLinkedToTemplates),
                  type: InputType.Switch
                }
              ])
        ],
        name: 'templates-block'
      }),
      timezone: {
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
    };
  },
  getSchema: ({ isMassChange, t }) => ({
    ...(isMassChange
      ? {
          poller: object({ id: number(), name: string() }).nullable()
        }
      : getHostOwnSchema(t)),
    checkOptions: object({
      macros: getMacrosSchema({ prefix: macroPrefix, t })
    }),
    schedulingOptions: object({
      maxCheckAttempts: getAtLeastOneSchema(t(labelMustBeIntegerOfAtLeastOne)),
      normalCheckInterval: getAtLeastOneSchema(
        t(labelMustBeIntegerOfAtLeastOne)
      ),
      retryCheckInterval: getAtLeastOneSchema(t(labelMustBeIntegerOfAtLeastOne))
    }),
    snmpCommunity: string().max(snmpCommunityMaxLength)
  }),
  toPayload: (values, { isCloudPlatform }) => {
    const {
      name,
      alias,
      address,
      checkOptions = defaultCheckOptions,
      createServicesLinkedToTemplates = true,
      poller,
      schedulingOptions = defaultSchedulingOptions,
      snmpCommunity,
      snmpVersion,
      templates = [],
      timezone
    } = values as {
      address: string;
      alias: string;
      checkOptions?: CheckOptionsValues;
      createServicesLinkedToTemplates?: boolean;
      name: string;
      poller: { id: number } | null;
      schedulingOptions?: SchedulingOptionsValues;
      snmpCommunity?: string;
      snmpVersion?: SnmpVersionOption | null;
      templates?: Array<TemplateRow>;
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
        command_id: checkOptions.command?.id ?? null,
        macros: toMacrosPayload(checkOptions.macros)
      },
      // Refused on cloud, which always creates them.
      ...(!isCloudPlatform && {
        create_services_linked_to_templates: createServicesLinkedToTemplates
      }),
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
      // In order, rows left unpicked aside.
      template_ids: toIds(
        templates.filter((template): template is NamedEntity => !!template)
      ),
      timezone_id: timezone?.id ?? null
    };
  }
};
