import { InputType, type SelectEntry } from '@centreon/ui';

import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { array, number, object, string } from 'yup';

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
  labelAlreadyExists,
  labelArgs,
  labelCheckCommand,
  labelCheckPeriod,
  labelCreateServicesLinkedToTemplates,
  labelCustomMacros,
  labelDescription,
  labelHostConfiguration,
  labelInvalidAddress,
  labelIpAddress,
  labelMaxCheckAttempts,
  labelMonitoringServer,
  labelMustBeAtMostCharacters,
  labelMustBeIntegerOfAtLeastOne,
  labelName,
  labelNameContainsForbiddenCharacters,
  labelNameMustNotStartWithModule,
  labelNormalCheckInterval,
  labelPassiveChecksEnabled,
  labelRequired,
  labelResolve,
  labelRetryCheckInterval,
  labelSnmpCommunity,
  labelSnmpVersion,
  labelTemplates,
  labelTimezone,
  labelValue
} from '../../translatedLabels';
import { argumentsToText, textToArguments } from '../commandArguments';
import Macros, { type MacroRow } from '../Macros';
import ResolveAddress from '../ResolveAddress';
import { buildSelector, toIds } from '../selector';
import Templates, { type TemplateRow } from '../Templates';
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
// `HostMacroInput`: the name once wrapped in `$_HOST…$` fits 255 characters,
// the value 4096 characters, the description 65535 bytes.
const macroNameMaxLength = 248;
const macroValueMaxLength = 4096;
const macroDescriptionMaxBytes = 65535;
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

// A number field holds `''` until something is typed.
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

// As the server stores the name: trimmed, upper-cased.
const toMacroName = (name: string): string => name.trim().toUpperCase();

interface MacroDetail {
  description: string;
  isPassword: boolean;
  name: string;
  value?: string | null;
}

const macroDecoder = JsonDecoder.object<MacroDetail>(
  {
    description: JsonDecoder.optional(
      JsonDecoder.nullable(JsonDecoder.string)
    ).map((value) => value ?? ''),
    isPassword: JsonDecoder.optional(JsonDecoder.boolean).map(
      (value) => value ?? false
    ),
    name: JsonDecoder.string,
    // Never sent back for a password.
    value: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string))
  },
  'Macro',
  { isPassword: 'is_password' }
).map(
  ({ value, ...macro }): MacroRow => ({
    ...macro,
    hasStoredPassword: macro.isPassword,
    value: value ?? ''
  })
);

const toMacroPayload = ({
  description,
  hasStoredPassword,
  isPassword,
  name,
  value
}: MacroRow) => ({
  description: description || null,
  is_password: isPassword,
  name: toMacroName(name),
  // A password left empty is the one already stored: none is sent, rather
  // than a fake value the server would store.
  ...(!(isPassword && hasStoredPassword && value === '') && { value })
});

// The detail endpoint leaves unset values out rather than sending null.
const optionalNumberDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
).map((value): OptionalNumber => value ?? '');

// Left out on cloud, where the server keeps them unset.
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

const getMacrosSchema = (t: TFunction) =>
  array()
    .of(
      object({
        description: string().test(
          'fits-storage',
          t(labelMustBeAtMostCharacters, {
            label: t(labelDescription),
            max: macroDescriptionMaxBytes
          }),
          // Bytes, as the server counts them.
          (value) =>
            new TextEncoder().encode(value ?? '').length <=
            macroDescriptionMaxBytes
        ),
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
      // Templates beside the host's own macros.
      {
        fieldName: 'templates-layout',
        grid: {
          className: 'grid-cols-1 gap-x-8 @[800px]:grid-cols-2',
          columns: [
            {
              fieldName: 'templates-block',
              grid: {
                className: 'grid-cols-1',
                columns: [
                  {
                    custom: { Component: Templates },
                    dataTestId: 'host-form-templates',
                    fieldName: 'templates',
                    label: t(labelTemplates),
                    type: InputType.Custom
                  },
                  // Cloud always creates them.
                  ...(isCloudPlatform
                    ? []
                    : [
                        {
                          dataTestId:
                            'host-form-create-services-linked-to-templates',
                          fieldName: 'createServicesLinkedToTemplates',
                          label: t(labelCreateServicesLinkedToTemplates),
                          type: InputType.Switch
                        }
                      ])
                ]
              },
              label: 'host-form-templates-block',
              type: InputType.Grid
            },
            {
              custom: { Component: Macros },
              dataTestId: 'host-form-check-options-macros',
              fieldName: 'checkOptions.macros',
              label: t(labelCustomMacros),
              type: InputType.Custom
            }
          ]
        },
        label: 'host-form-templates-layout',
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
                    autocomplete: { options: snmpVersionOptions },
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
                // Enabling checks is an onPrem setting; cloud has the check
                // period and numbers alone, two by two.
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
    checkOptions: object({ macros: getMacrosSchema(t) }),
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
        macros: (checkOptions.macros ?? []).map(toMacroPayload)
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
      // In order, rows left unpicked aside.
      template_ids: toIds(
        templates.filter((template): template is NamedEntity => !!template)
      ),
      timezone_id: timezone?.id ?? null
    };
  }
};
