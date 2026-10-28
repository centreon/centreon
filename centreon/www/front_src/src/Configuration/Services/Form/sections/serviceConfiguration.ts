import {
  buildListingDecoder,
  type InputProps,
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

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
  labelCheckPeriod,
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
import type { FormSection } from './models';

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

export const serviceConfiguration: FormSection<ServiceConfigurationDetail> = {
  defaultValues: {
    // Empty even when the form opens from a host (decision D8).
    host: null,
    // A service is created enabled; the header switch changes it afterwards.
    isActivated: true,
    name: '',
    schedulingOptions: defaultSchedulingOptions,
    template: null
  },
  detailDecoders: {
    host: optionalNamedEntityDecoder('Host'),
    isActivated: JsonDecoder.boolean,
    name: JsonDecoder.string,
    schedulingOptions: JsonDecoder.optional(
      JsonDecoder.nullable(schedulingOptionsDecoder)
    ).map((value) => value ?? defaultSchedulingOptions),
    template: optionalNamedEntityDecoder('Template')
  },
  detailKeyMap: {
    isActivated: 'activated',
    schedulingOptions: 'scheduling_options'
  },
  getInputs: ({ isCloudPlatform, isMassChange, t }) => [
    ...(isMassChange ? [] : getIdentityInputs({ isCloudPlatform, t })),
    ...getSchedulingInputs({ isCloudPlatform, t })
  ],
  getSchema: ({ isCloudPlatform, isMassChange, t }) => ({
    ...(isMassChange ? {} : getIdentitySchema({ isCloudPlatform, t })),
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
