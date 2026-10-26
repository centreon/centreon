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
import { servicesBaseEndpoint } from '../../api/endpoints';
import type { NamedEntity } from '../../models';
import {
  labelArgs,
  labelArgumentsForbiddenTokens,
  labelCheckCommand,
  labelCheckCommandTemplateCustomMacros,
  labelCustomMacros,
  labelFillAllArgumentsOrNone,
  labelRequired,
  labelTemplate
} from '../../translatedLabels';
import ServiceMacros from '../ServiceMacros';
import type { FormSection, FormValues, SectionContext } from './models';
import {
  buildSelector,
  namedEntitySchema,
  optionalNamedEntityDecoder
} from './selector';

// Placeholder until the backend exposes the form's own selectors, modeled on
// the `/configuration/hosts/*` ones: granted by service write access, not by
// each object's ACL.
export const serviceFormServiceTemplatesEndpoint =
  '/configuration/services/service_templates';

interface CheckOptionsDetail {
  // MON-213083: F04 to F06.
  checkCommand: CheckCommand | null;
  checkOptions: CheckOptionsValues;
  template: NamedEntity | null;
}

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

// The command the service runs. Advanced (F30, F31) reads
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

// The template (F03): a command it brings counts as the service's own.
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

// The Figma panel's second section: the command, the template (F03, a single
// one: a service has one direct template) and the macros. Command and
// template on the left, arguments and macros on the right.
const getCheckOptionsInputs = ({
  isCloudPlatform,
  isMassChange,
  t
}: SectionContext): Array<InputPropsWithoutGroup> => [
  {
    fieldName: 'check-options',
    grid: {
      className: 'grid-cols-1 gap-x-8 @[800px]:grid-cols-2',
      columns: [
        {
          fieldName: 'check-options-command',
          grid: {
            className: 'grid-cols-1',
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
              // One service, one template: a mass change does not move it,
              // as before the regrouping.
              ...(isMassChange
                ? []
                : [
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
                  ])
            ] as Array<InputProps>
          },
          label: 'service-form-check-options-command',
          type: InputType.Grid
        },
        {
          fieldName: 'check-options-arguments',
          grid: {
            className: 'grid-cols-1',
            columns: [
              // Arguments go with a command, the service's own or its
              // template's: the usual case is a template's command with the
              // service's own arguments.
              {
                dataTestId: 'service-form-args',
                fieldName: 'checkOptions.args',
                hideInput: (values) => !hasCheckCommand(values),
                label: t(labelArgs),
                text: { placeholder: '!arg1!arg2' },
                type: InputType.Text
              },
              // Not in a mass change, as on hosts: an upsert matches macros
              // by id only.
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
            ] as Array<InputProps>
          },
          label: 'service-form-check-options-arguments',
          type: InputType.Grid
        }
      ]
    },
    label: 'service-form-check-options',
    type: InputType.Grid
  }
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

export const checkOptions: FormSection<CheckOptionsDetail> = {
  defaultValues: {
    checkCommand: null,
    checkOptions: defaultCheckOptions,
    template: null
  },
  detailDecoders: {
    checkCommand: checkCommandFromCheckOptionsDecoder,
    checkOptions: JsonDecoder.optional(
      JsonDecoder.nullable(checkOptionsDecoder)
    ).map((value) => value ?? defaultCheckOptions),
    template: optionalNamedEntityDecoder('Template')
  },
  // The command and the arguments with macros both read `check_options`: the
  // command sits apart in the form.
  detailKeyMap: {
    checkCommand: 'check_options',
    checkOptions: 'check_options'
  },
  getInputs: getCheckOptionsInputs,
  getSchema: ({ isCloudPlatform, isMassChange, t }) => ({
    ...getCheckOptionsSchema({ isMassChange, t }),
    // The API already requires it on cloud. A mass change has no template.
    ...(!isMassChange && {
      template: isCloudPlatform
        ? namedEntitySchema.required(t(labelRequired))
        : namedEntitySchema
    })
  }),
  label: labelCheckCommandTemplateCustomMacros,
  toPayload: (values) => {
    const { template } = values as { template?: NamedEntity | null };

    return {
      ...toCheckOptionsPayload(values),
      service_template_id: template?.id ?? null
    };
  }
};
