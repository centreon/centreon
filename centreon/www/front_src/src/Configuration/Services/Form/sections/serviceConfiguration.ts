import {
  buildListingDecoder,
  type InputProps,
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import type { FormikValues } from 'formik';
import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { array, number, object, string, type TestContext } from 'yup';

import {
  getMacrosSchema,
  isUnchangedInherited,
  type MacroRow,
  macroDecoder,
  toMacrosPayload
} from '../../../ConfigurationBase/Form/Macros';
import {
  argumentsToText,
  textToArguments
} from '../../../Hosts/Form/commandArguments';
import {
  defaultTriState,
  type TriState,
  triStateDecoder,
  triStateOptions
} from '../../../Hosts/Form/triState';
import { servicesBaseEndpoint } from '../../api/endpoints';
import type { NamedEntity } from '../../models';
import {
  labelActiveChecksEnabled,
  labelArgs,
  labelArgumentsForbiddenTokens,
  labelCheckCommand,
  labelCheckPeriod,
  labelCustomMacros,
  labelFillAllArgumentsOrNone,
  labelHost,
  labelIsVolatile,
  labelMaxCheckAttempts,
  labelMustBeAtMostCharacters,
  labelMustBeIntegerOfAtLeastOne,
  labelName,
  labelNameContainsForbiddenCharacters,
  labelNormalCheckInterval,
  labelPassiveChecksEnabled,
  labelRequired,
  labelRetryCheckInterval,
  labelServiceConfiguration,
  labelTemplate
} from '../../translatedLabels';
import ServiceMacros from '../ServiceMacros';
import type { FormSection, FormValues, SectionContext } from './models';

// F01: uniqueness on the host is left to the server, which alone sees the
// other services of that host.
const nameMaxLength = 200;
// The set the legacy `illegal_object_name_chars` option strips, which the API
// refuses instead.
const forbiddenNameCharacters = /^[^~!$%^&*"|'<>?,()=]*$/;

// Written as is on a line of the engine configuration: no control character.
const hasControlCharacter = (value: string): boolean =>
  [...value].some((character) => {
    const code = character.charCodeAt(0);

    return code < 32 || code === 127;
  });

// What the server stores: trimmed, inner runs of spaces collapsed.
const normalizeName = (name: string | undefined): string =>
  (name ?? '').trim().replace(/\s{2,}/g, ' ');

// Placeholders until the backend exposes the form's own selectors, modeled on
// the `/configuration/hosts/*` ones: granted by service write access, not by
// each object's ACL.
export const serviceFormHostsEndpoint = '/configuration/services/hosts';
export const serviceFormServiceTemplatesEndpoint =
  '/configuration/services/service_templates';
export const serviceFormTimePeriodsEndpoint =
  '/configuration/services/timeperiods';

type ConnectedAutocomplete = NonNullable<InputProps['connectedAutocomplete']>;

const namedEntityDecoder = JsonDecoder.object<NamedEntity>(
  { id: JsonDecoder.number, name: JsonDecoder.string },
  'Entity'
);

// The selectors answer in Hydra, which the autocomplete cannot read unmapped.
const selectorDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: namedEntityDecoder,
  entityDecoderName: 'Entity',
  listingDecoderName: 'Entity List'
});

const buildSelector = ({
  endpoint,
  queryKey
}: {
  endpoint: string;
  queryKey: string;
}): ConnectedAutocomplete => ({
  additionalConditionParameters: [],
  baseEndpoint: servicesBaseEndpoint,
  customQueryParameters: [],
  decoder: selectorDecoder,
  endpoint,
  getOptionLabel: (option) => (option as SelectEntry)?.name,
  queryKey,
  useNewAPIFormat: true
});

type OptionalNumber = number | '';

interface SchedulingOptionsValues {
  activeCheckEnabled: TriState;
  checkPeriod: NamedEntity | null;
  isVolatile: TriState;
  maxCheckAttempts: OptionalNumber;
  normalCheckInterval: OptionalNumber;
  passiveCheckEnabled: TriState;
  retryCheckInterval: OptionalNumber;
}

interface ServiceConfigurationDetail {
  // MON-213083: F04 to F06.
  checkCommand: CheckCommand | null;
  checkOptions: CheckOptionsValues;
  host: NamedEntity | null;
  // Read for the panel header's switch, which enables and disables the
  // service on its own request: the form never sends it.
  isActivated: boolean;
  name: string;
  schedulingOptions: SchedulingOptionsValues;
  template: NamedEntity | null;
}

const defaultSchedulingOptions: SchedulingOptionsValues = {
  activeCheckEnabled: defaultTriState,
  checkPeriod: null,
  isVolatile: defaultTriState,
  maxCheckAttempts: '',
  normalCheckInterval: '',
  passiveCheckEnabled: defaultTriState,
  retryCheckInterval: ''
};

const optionalNamedEntityDecoder = (name: string) =>
  JsonDecoder.optional(
    JsonDecoder.nullable(
      JsonDecoder.object<NamedEntity>(
        { id: JsonDecoder.number, name: JsonDecoder.string },
        name
      )
    )
  ).map((value) => value ?? null);

const optionalNumberDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
).map((value): OptionalNumber => value ?? '');

// Absent means Default, as the API reads it.
const optionalTriStateDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(triStateDecoder)
).map((value) => value ?? defaultTriState);

const schedulingOptionsDecoder = JsonDecoder.object<SchedulingOptionsValues>(
  {
    activeCheckEnabled: optionalTriStateDecoder,
    checkPeriod: optionalNamedEntityDecoder('Check period'),
    isVolatile: optionalTriStateDecoder,
    maxCheckAttempts: optionalNumberDecoder,
    normalCheckInterval: optionalNumberDecoder,
    passiveCheckEnabled: optionalTriStateDecoder,
    retryCheckInterval: optionalNumberDecoder
  },
  'Scheduling options',
  {
    activeCheckEnabled: 'active_check_enabled',
    checkPeriod: 'check_period',
    isVolatile: 'is_volatile',
    maxCheckAttempts: 'max_check_attempts',
    normalCheckInterval: 'normal_check_interval',
    passiveCheckEnabled: 'passive_check_enabled',
    retryCheckInterval: 'retry_check_interval'
  }
);

// Empty leaves the value to the template chain.
const toApiNumber = (value: OptionalNumber | undefined): number | null =>
  value === '' || value === undefined ? null : Number(value);

const getAtLeastOneSchema = (message: string) =>
  number()
    .transform((value, originalValue) => (originalValue === '' ? null : value))
    .nullable()
    .typeError(message)
    .integer(message)
    .min(1, message);

const namedEntitySchema = object({ id: number(), name: string() }).nullable();

// F01 to F03: the fields that name one service. A mass change edits a
// selection, so it neither names nor moves them.
const getIdentityInputs = ({
  isCloudPlatform,
  t
}: {
  isCloudPlatform: boolean;
  t: TFunction;
}): Array<InputPropsWithoutGroup> => [
  {
    fieldName: 'service-identity',
    grid: {
      className: 'grid-cols-1 @[600px]:grid-cols-3',
      columns: [
        {
          dataTestId: 'service-form-name',
          fieldName: 'name',
          label: t(labelName),
          required: true,
          type: InputType.Text
        },
        {
          connectedAutocomplete: buildSelector({
            endpoint: serviceFormHostsEndpoint,
            queryKey: 'service-form-host'
          }),
          dataTestId: 'service-form-host',
          fieldName: 'host',
          label: t(labelHost),
          required: true,
          type: InputType.SingleConnectedAutocomplete
        },
        {
          connectedAutocomplete: buildSelector({
            endpoint: serviceFormServiceTemplatesEndpoint,
            queryKey: 'service-form-template'
          }),
          dataTestId: 'service-form-template',
          fieldName: 'template',
          label: t(labelTemplate),
          required: isCloudPlatform,
          type: InputType.SingleConnectedAutocomplete
        }
      ]
    },
    label: 'service-form-identity',
    type: InputType.Grid
  }
];

const getSchedulingNumberInput = ({
  dataTestId,
  fieldName,
  label
}: {
  dataTestId: string;
  fieldName: keyof SchedulingOptionsValues;
  label: string;
}): InputPropsWithoutGroup => ({
  dataTestId,
  fieldName: `schedulingOptions.${fieldName}`,
  label,
  text: { min: 1, type: 'number' },
  type: InputType.Text
});

const getSchedulingTriStateInput = ({
  dataTestId,
  fieldName,
  label
}: {
  dataTestId: string;
  fieldName: keyof SchedulingOptionsValues;
  label: string;
}): InputPropsWithoutGroup => ({
  dataTestId,
  fieldName: `schedulingOptions.${fieldName}`,
  label,
  segmentedButtons: { options: triStateOptions },
  type: InputType.SegmentedButtons
});

// F07 to F13. The Yes/No/Default toggles do not exist on cloud.
const getSchedulingInputs = ({
  isCloudPlatform,
  t
}: {
  isCloudPlatform: boolean;
  t: TFunction;
}): Array<InputPropsWithoutGroup> => {
  const checkNumbers: Array<InputPropsWithoutGroup> = [
    {
      connectedAutocomplete: buildSelector({
        endpoint: serviceFormTimePeriodsEndpoint,
        queryKey: 'service-form-check-period'
      }),
      dataTestId: 'service-form-check-period',
      fieldName: 'schedulingOptions.checkPeriod',
      label: t(labelCheckPeriod),
      type: InputType.SingleConnectedAutocomplete
    },
    getSchedulingNumberInput({
      dataTestId: 'service-form-max-check-attempts',
      fieldName: 'maxCheckAttempts',
      label: t(labelMaxCheckAttempts)
    }),
    getSchedulingNumberInput({
      dataTestId: 'service-form-normal-check-interval',
      fieldName: 'normalCheckInterval',
      label: t(labelNormalCheckInterval)
    }),
    getSchedulingNumberInput({
      dataTestId: 'service-form-retry-check-interval',
      fieldName: 'retryCheckInterval',
      label: t(labelRetryCheckInterval)
    })
  ];

  return [
    {
      fieldName: 'service-scheduling-options',
      grid: {
        className: 'grid-cols-1 gap-x-8 @[600px]:grid-cols-2',
        columns: isCloudPlatform
          ? checkNumbers
          : [
              {
                fieldName: 'service-scheduling-check-numbers',
                grid: { className: 'grid-cols-1', columns: checkNumbers },
                label: 'service-form-scheduling-check-numbers',
                type: InputType.Grid
              },
              {
                fieldName: 'service-scheduling-checks-enabled',
                grid: {
                  className: 'grid-cols-1',
                  columns: [
                    getSchedulingTriStateInput({
                      dataTestId: 'service-form-active-checks-enabled',
                      fieldName: 'activeCheckEnabled',
                      label: t(labelActiveChecksEnabled)
                    }),
                    getSchedulingTriStateInput({
                      dataTestId: 'service-form-passive-checks-enabled',
                      fieldName: 'passiveCheckEnabled',
                      label: t(labelPassiveChecksEnabled)
                    }),
                    getSchedulingTriStateInput({
                      dataTestId: 'service-form-is-volatile',
                      fieldName: 'isVolatile',
                      label: t(labelIsVolatile)
                    })
                  ]
                },
                label: 'service-form-scheduling-checks-enabled',
                type: InputType.Grid
              }
            ]
      },
      label: 'service-form-scheduling-options',
      type: InputType.Grid
    }
  ];
};

const getIdentitySchema = ({
  isCloudPlatform,
  t
}: {
  isCloudPlatform: boolean;
  t: TFunction;
}) => ({
  host: namedEntitySchema.required(t(labelRequired)),
  // Trimmed as the server normalises it, so blanks report as missing.
  name: string()
    .trim()
    .max(
      nameMaxLength,
      t(labelMustBeAtMostCharacters, {
        label: t(labelName),
        max: nameMaxLength
      })
    )
    .matches(forbiddenNameCharacters, t(labelNameContainsForbiddenCharacters))
    .test(
      'has-no-control-character',
      t(labelNameContainsForbiddenCharacters),
      (value) => !hasControlCharacter(value ?? '')
    )
    .required(t(labelRequired)),
  // The API already requires it on cloud.
  template: isCloudPlatform
    ? namedEntitySchema.required(t(labelRequired))
    : namedEntitySchema
});

// MON-213083: check command, arguments and macros (F04–F06).

// Placeholder until the backend exposes it, on the model of the hosts'
// `/configuration/hosts/commands`: the commands a service may run, granted by
// service write access.
export const serviceFormCommandsEndpoint = '/configuration/services/commands';

// `ServiceMacroInput` to come: the name once wrapped in `$_SERVICE…$` fits 255
// characters, and the criticality macros belong to the server.
const macroPrefix = '$_SERVICE';
const reservedMacroNames = [
  '$_SERVICECRITICALITY_LEVEL$',
  '$_SERVICECRITICALITY_ID$'
];

// What the server writes for a new line, a tab and a carriage return: an
// argument holding one would come back changed.
const forbiddenArgumentTokens = /#BR#|#T#|#R#/;

// The command the service runs. Data Processing (F30, F31) reads
// `isCentreonMonitoringAgent` from it to force the freshness check.
export interface CheckCommand extends NamedEntity {
  isCentreonMonitoringAgent: boolean;
}

// The rest of the API's `check_options`, as on a host: the service's macros
// belong to it too.
interface CheckOptionsValues {
  args: string;
  macros: Array<MacroRow>;
}

const defaultCheckOptions: CheckOptionsValues = {
  args: '',
  macros: []
};

// Placeholder key `is_centreon_monitoring_agent`: the server tells a Centreon
// Monitoring Agent command by its connector and name
// (`Command::isCentreonMonitoringAgent`), which the client cannot redo.
const checkCommandDecoder = JsonDecoder.object<CheckCommand>(
  {
    id: JsonDecoder.number,
    isCentreonMonitoringAgent: JsonDecoder.optional(JsonDecoder.boolean).map(
      (value) => value ?? false
    ),
    name: JsonDecoder.string
  },
  'Check command',
  { isCentreonMonitoringAgent: 'is_centreon_monitoring_agent' }
);

// Kept here rather than in `api/decoders`, which imports the sections.
const commandsListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: checkCommandDecoder,
  entityDecoderName: 'Command',
  listingDecoderName: 'Command List'
});

const checkOptionsDecoder = JsonDecoder.object<CheckOptionsValues>(
  {
    args: JsonDecoder.optional(
      JsonDecoder.array(JsonDecoder.string, 'Check command arguments')
    ).map((value) => argumentsToText(value ?? [])),
    macros: JsonDecoder.optional(JsonDecoder.array(macroDecoder, 'Macros')).map(
      (value) => value ?? []
    )
  },
  'Check options'
);

// `check_options.command`, lifted to the form's `checkCommand`.
const checkCommandFromCheckOptionsDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(
    JsonDecoder.object<{ command: CheckCommand | null }>(
      {
        command: JsonDecoder.optional(
          JsonDecoder.nullable(checkCommandDecoder)
        ).map((value) => value ?? null)
      },
      'Check options'
    )
  )
).map((value) => value?.command ?? null);

// The template field (F03) is another sub-task's: read here, never written.
const hasTemplate = (values: FormikValues | undefined): boolean =>
  !!values?.template;

// A command the service runs, its own or its template's. A template always
// brings one: the server refuses a template line without any (F03).
const hasCheckCommand = (values: FormikValues | undefined): boolean =>
  !!values?.checkCommand || hasTemplate(values);

// The whole form's values, from a rule on one of its fields: the outermost
// ancestor (an object schema lists itself first, a string does not).
const getFormValues = (context: TestContext): FormikValues | undefined =>
  context.from?.[context.from.length - 1]?.value;

const getArgumentsSchema = (t: TFunction) =>
  string()
    .test(
      'no-escape-tokens',
      t(labelArgumentsForbiddenTokens),
      (value) => !forbiddenArgumentTokens.test(value ?? '')
    )
    // Legacy's rule, kept: `!a!!c` misses its second argument.
    .test(
      'all-or-none',
      t(labelFillAllArgumentsOrNone),
      function checkAllOrNone(value) {
        // Hidden, and not sent, without a command.
        if (!hasCheckCommand(getFormValues(this))) {
          return true;
        }

        const args = textToArguments(value ?? '');

        return (
          args.every((argument) => argument === '') ||
          args.every((argument) => argument !== '')
        );
      }
    );

// A value, unless the row is still what the service inherits (not sent) or a
// stored password left as is (sent as null, the server keeps it).
const isMissingValue = (macro: MacroRow): boolean =>
  !isUnchangedInherited(macro) &&
  !(macro.hasStoredPassword && !macro.isErased) &&
  (macro.value ?? '') === '';

const getServiceMacrosSchema = (t: TFunction) =>
  getMacrosSchema({
    prefix: macroPrefix,
    reservedNames: reservedMacroNames,
    t
  }).test('values-required', function checkValuesRequired(macros) {
    const index = ((macros ?? []) as Array<MacroRow>).findIndex(isMissingValue);

    return (
      index === -1 ||
      this.createError({
        message: t(labelRequired),
        path: `${this.path}[${index}].value`
      })
    );
  });

export const getCheckOptionsSchema = ({
  isMassChange,
  t
}: Pick<SectionContext, 'isMassChange' | 't'>) => ({
  checkCommand: object({ id: number(), name: string() })
    .nullable()
    // Not yup's `{}` for an object left out, which would pass for a command.
    .default(null)
    // Without a template, nothing else gives the service a command. A mass
    // change requires nothing.
    .test(
      'required-without-template',
      t(labelRequired),
      function checkCommandRequired(command) {
        return !!isMassChange || !!command || hasTemplate(getFormValues(this));
      }
    ),
  checkOptions: object({
    args: getArgumentsSchema(t),
    macros: isMassChange ? array() : getServiceMacrosSchema(t)
  })
});

const getCheckOptionsInputs = ({
  isMassChange,
  t
}: SectionContext): Array<InputPropsWithoutGroup> => [
  {
    fieldName: 'check-options',
    grid: {
      className: 'grid-cols-1 gap-x-8 @[800px]:grid-cols-2',
      columns: [
        {
          connectedAutocomplete: {
            additionalConditionParameters: [],
            baseEndpoint: servicesBaseEndpoint,
            customQueryParameters: [
              { name: 'type[]', value: 'Check' },
              { name: 'is_activated', value: true }
            ],
            decoder: commandsListDecoder,
            endpoint: serviceFormCommandsEndpoint,
            getOptionLabel: (option) => (option as SelectEntry)?.name,
            queryKey: 'service-form-check-command',
            useNewAPIFormat: true
          },
          dataTestId: 'service-form-check-command',
          fieldName: 'checkCommand',
          getRequired: (values) => !isMassChange && !hasTemplate(values),
          label: t(labelCheckCommand),
          type: InputType.SingleConnectedAutocomplete
        },
        // Arguments go with a command, the service's own or its template's:
        // the usual case is a template's command with the service's own
        // arguments.
        {
          dataTestId: 'service-form-args',
          fieldName: 'checkOptions.args',
          hideInput: (values) => !hasCheckCommand(values),
          label: t(labelArgs),
          text: { placeholder: '!arg1!arg2' },
          type: InputType.Text
        }
      ] as Array<InputProps>
    },
    label: 'service-form-check-options',
    type: InputType.Grid
  },
  // Not in a mass change, as on hosts: an upsert matches macros by id only.
  ...(isMassChange
    ? []
    : [
        {
          custom: { Component: ServiceMacros },
          dataTestId: 'service-form-macros',
          fieldName: 'checkOptions.macros',
          label: t(labelCustomMacros),
          type: InputType.Custom
        }
      ])
];

export const toCheckOptionsPayload = (values: FormValues) => {
  const { checkCommand = null, checkOptions = defaultCheckOptions } =
    values as {
      checkCommand?: CheckCommand | null;
      checkOptions?: CheckOptionsValues;
    };

  return {
    check_options: {
      // Left over from a command since removed, they would be refused.
      args: hasCheckCommand(values)
        ? textToArguments(checkOptions.args ?? '')
        : [],
      command_id: checkCommand?.id ?? null,
      macros: toMacrosPayload(checkOptions.macros)
    }
  };
};

export const serviceConfiguration: FormSection<ServiceConfigurationDetail> = {
  defaultValues: {
    checkCommand: null,
    checkOptions: defaultCheckOptions,
    // Empty even when the form opens from a host (decision D8).
    host: null,
    // A service is created enabled; the header switch changes it afterwards.
    isActivated: true,
    name: '',
    schedulingOptions: defaultSchedulingOptions,
    template: null
  },
  detailDecoders: {
    checkCommand: checkCommandFromCheckOptionsDecoder,
    checkOptions: JsonDecoder.optional(
      JsonDecoder.nullable(checkOptionsDecoder)
    ).map((value) => value ?? defaultCheckOptions),
    host: optionalNamedEntityDecoder('Host'),
    isActivated: JsonDecoder.boolean,
    name: JsonDecoder.string,
    schedulingOptions: JsonDecoder.optional(
      JsonDecoder.nullable(schedulingOptionsDecoder)
    ).map((value) => value ?? defaultSchedulingOptions),
    template: optionalNamedEntityDecoder('Template')
  },
  // The command and the arguments with macros both read `check_options`: the
  // command sits apart in the form.
  detailKeyMap: {
    checkCommand: 'check_options',
    checkOptions: 'check_options',
    isActivated: 'activated',
    schedulingOptions: 'scheduling_options'
  },
  // In the legacy tab's order: F01 to F03, F04 to F06, then F07 to F14.
  getInputs: (context) => [
    ...(context.isMassChange
      ? []
      : getIdentityInputs({
          isCloudPlatform: context.isCloudPlatform,
          t: context.t
        })),
    ...getCheckOptionsInputs(context),
    ...getSchedulingInputs({
      isCloudPlatform: context.isCloudPlatform,
      t: context.t
    })
  ],
  getSchema: ({ isCloudPlatform, isMassChange, t }) => ({
    ...(isMassChange ? {} : getIdentitySchema({ isCloudPlatform, t })),
    ...getCheckOptionsSchema({ isMassChange, t }),
    schedulingOptions: object({
      maxCheckAttempts: getAtLeastOneSchema(t(labelMustBeIntegerOfAtLeastOne)),
      normalCheckInterval: getAtLeastOneSchema(
        t(labelMustBeIntegerOfAtLeastOne)
      ),
      retryCheckInterval: getAtLeastOneSchema(t(labelMustBeIntegerOfAtLeastOne))
    })
  }),
  label: labelServiceConfiguration,
  toPayload: (values, { isCloudPlatform, isMassChange }) => {
    const {
      host,
      name,
      schedulingOptions = defaultSchedulingOptions,
      template
    } = values as {
      host?: NamedEntity | null;
      name?: string;
      schedulingOptions?: SchedulingOptionsValues;
      template?: NamedEntity | null;
    };

    return {
      ...toCheckOptionsPayload(values),
      // A mass change has neither field.
      ...(!isMassChange && {
        host_id: host?.id ?? null,
        name: normalizeName(name)
      }),
      scheduling_options: {
        check_timeperiod_id: schedulingOptions.checkPeriod?.id ?? null,
        max_check_attempts: toApiNumber(schedulingOptions.maxCheckAttempts),
        normal_check_interval: toApiNumber(
          schedulingOptions.normalCheckInterval
        ),
        retry_check_interval: toApiNumber(schedulingOptions.retryCheckInterval),
        // Refused on cloud rather than ignored.
        ...(!isCloudPlatform && {
          active_check_enabled: schedulingOptions.activeCheckEnabled,
          is_volatile: schedulingOptions.isVolatile,
          passive_check_enabled: schedulingOptions.passiveCheckEnabled
        })
      },
      service_template_id: template?.id ?? null
    };
  }
};
