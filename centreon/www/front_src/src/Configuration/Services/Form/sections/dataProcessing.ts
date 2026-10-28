import {
  buildListingDecoder,
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import type { TFunction } from 'i18next';
import { createElement } from 'react';
import { JsonDecoder } from 'ts.data.json';
import { number, object, string } from 'yup';

import { servicesBaseEndpoint } from '../../api/endpoints';
import type { NamedEntity } from '../../models';
import {
  labelAcknowledgementTimeout,
  labelArgs,
  labelCheckFreshness,
  labelCritical,
  labelDataProcessing,
  labelDefault,
  labelEventHandler,
  labelEventHandlerArgumentsNeedAnEventHandler,
  labelEventHandlerEnabled,
  labelFlapDetectionEnabled,
  labelFlappingOptions,
  labelForbiddenEventHandlerArguments,
  labelFreshnessControlOptions,
  labelFreshnessThreshold,
  labelHighFlapThreshold,
  labelHistoryOptions,
  labelLowFlapThreshold,
  labelMustBeIntegerBetweenZeroAndOneHundred,
  labelMustBeIntegerOfAtLeastOne,
  labelMustBePositiveIntegerOrZero,
  labelNo,
  labelObsessOverService,
  labelOk,
  labelRetainNonStatusInformation,
  labelRetainStatusInformation,
  labelSeconds,
  labelStalkingOptions,
  labelUnknown,
  labelWarning,
  labelYes
} from '../../translatedLabels';
import type { FormSection, FormValues } from './models';
import { serviceFormCommandsEndpoint } from './serviceConfiguration';

// Copied from `Hosts/Form/triState.ts`, `commandArguments.ts` and
// `selector.ts` rather than imported, as the skeleton copied the section
// contract: the Services module does not depend on the Hosts one.

// The three states as the API spells them. Default is a value of its own: it
// leaves the directive to the template chain, and is never No.
type TriState = 'true' | 'false' | 'use_default';

const defaultTriState: TriState = 'use_default';

const triStateOptions: Array<{ label: string; value: TriState }> = [
  { label: labelYes, value: 'true' },
  { label: labelNo, value: 'false' },
  { label: labelDefault, value: defaultTriState }
];

const triStateDecoder = JsonDecoder.oneOf<TriState>(
  triStateOptions.map(({ value }) => JsonDecoder.isExactly(value)),
  'TriState'
);

// Typed as legacy shows them, `!arg1!arg2`; the API takes and returns the list.
const argumentsToText = (args: Array<string>): string =>
  args.map((argument) => `!${argument}`).join('');

// The leading `!` is optional. Being the separator, `!` can never end up
// inside an argument.
const textToArguments = (text: string): Array<string> => {
  if (text === '') {
    return [];
  }

  const [first, ...rest] = text.split('!');

  return first === '' ? rest : [first, ...rest];
};

// What the engine reads as a line break, a tab or a carriage return.
const forbiddenArgumentTokens = ['#BR#', '#T#', '#R#'];

// Placeholder until the backend exposes it: modeled on
// `/configuration/hosts/commands`. No type filter, the event handler may be
// any command.
// The commands selector, shared with the check command (MON-213083); the
// event handler takes any type.
export { serviceFormCommandsEndpoint };

const namedEntitiesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: JsonDecoder.object<NamedEntity>(
    { id: JsonDecoder.number, name: JsonDecoder.string },
    'Entity'
  ),
  entityDecoderName: 'Entity',
  listingDecoderName: 'Entity List'
});

// A Centreon Monitoring Agent check command forces Check Freshness to Yes and
// the threshold to 500 s (`DataProcessing::withCentreonMonitoringAgentFreshness`).
const centreonMonitoringAgentFreshnessThreshold = 500;

// Placeholder until F04 (MON-213083) adds the check command: the field name
// `checkCommand` and its `isCentreonMonitoringAgent` flag are assumptions. The
// backend tells such a command by its connector and name.
const hasCentreonMonitoringAgentCommand = (values: FormValues): boolean =>
  (values.checkCommand as { isCentreonMonitoringAgent?: boolean } | null)
    ?.isCentreonMonitoringAgent === true;

// In the order the API normalizes them.
const stalkingOptions = [
  { label: labelOk, letter: 'o' },
  { label: labelWarning, letter: 'w' },
  { label: labelUnknown, letter: 'u' },
  { label: labelCritical, letter: 'c' }
] as const;

type StalkingOption = (typeof stalkingOptions)[number]['letter'];

// One checkbox input each, as `@centreon/ui` stores them: `CheckboxGroup`
// would empty the field once disabled, so read-only would show none.
type StalkingOptionsValues = Record<StalkingOption, { checked: boolean }>;

type OptionalNumber = number | '';

interface DataProcessingValues {
  acknowledgmentTimeout: OptionalNumber;
  checkFreshness: TriState;
  eventHandler: NamedEntity | null;
  // Typed as legacy shows it, `!arg1!arg2`; the API takes the list.
  eventHandlerArgs: string;
  eventHandlerEnabled: TriState;
  flapDetectionEnabled: TriState;
  freshnessThreshold: OptionalNumber;
  highFlapThreshold: OptionalNumber;
  lowFlapThreshold: OptionalNumber;
  obsessOverService: TriState;
  retainNonStatusInformation: TriState;
  retainStatusInformation: TriState;
  stalkingOptions: StalkingOptionsValues;
}

interface DataProcessingDetail {
  dataProcessing: DataProcessingValues;
}

const toStalkingOptionsValues = (
  letters: Array<string>
): StalkingOptionsValues =>
  Object.fromEntries(
    stalkingOptions.map(({ letter }) => [
      letter,
      { checked: letters.includes(letter) }
    ])
  ) as StalkingOptionsValues;

const defaultDataProcessing: DataProcessingValues = {
  acknowledgmentTimeout: '',
  checkFreshness: defaultTriState,
  eventHandler: null,
  eventHandlerArgs: '',
  eventHandlerEnabled: defaultTriState,
  flapDetectionEnabled: defaultTriState,
  freshnessThreshold: '',
  highFlapThreshold: '',
  lowFlapThreshold: '',
  obsessOverService: defaultTriState,
  retainNonStatusInformation: defaultTriState,
  retainStatusInformation: defaultTriState,
  stalkingOptions: toStalkingOptionsValues([])
};

const optionalNumberDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
).map((value): OptionalNumber => value ?? '');

const optionalTriStateDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(triStateDecoder)
).map((value) => value ?? defaultTriState);

// Fields left out on cloud are absent from the response: each one is optional.
const dataProcessingDecoder = JsonDecoder.object<DataProcessingValues>(
  {
    acknowledgmentTimeout: optionalNumberDecoder,
    checkFreshness: optionalTriStateDecoder,
    eventHandler: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object<NamedEntity>(
          { id: JsonDecoder.number, name: JsonDecoder.string },
          'Event handler'
        )
      )
    ).map((value) => value ?? null),
    eventHandlerArgs: JsonDecoder.optional(
      JsonDecoder.array(JsonDecoder.string, 'Event handler arguments')
    ).map((value) => argumentsToText(value ?? [])),
    eventHandlerEnabled: optionalTriStateDecoder,
    flapDetectionEnabled: optionalTriStateDecoder,
    freshnessThreshold: optionalNumberDecoder,
    highFlapThreshold: optionalNumberDecoder,
    lowFlapThreshold: optionalNumberDecoder,
    obsessOverService: optionalTriStateDecoder,
    retainNonStatusInformation: optionalTriStateDecoder,
    retainStatusInformation: optionalTriStateDecoder,
    stalkingOptions: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.array(JsonDecoder.string, 'Stalking options')
      )
    ).map((value) => toStalkingOptionsValues(value ?? []))
  },
  'Data processing',
  {
    // The API spells it without the legacy `e`, as for hosts.
    acknowledgmentTimeout: 'acknowledgment_timeout',
    checkFreshness: 'check_freshness',
    eventHandler: 'event_handler',
    eventHandlerArgs: 'event_handler_args',
    eventHandlerEnabled: 'event_handler_enabled',
    flapDetectionEnabled: 'flap_detection_enabled',
    freshnessThreshold: 'freshness_threshold',
    highFlapThreshold: 'high_flap_threshold',
    lowFlapThreshold: 'low_flap_threshold',
    obsessOverService: 'obsess_over_service',
    retainNonStatusInformation: 'retain_non_status_information',
    retainStatusInformation: 'retain_status_information',
    stalkingOptions: 'stalking_options'
  }
);

const toApiNumber = (value: OptionalNumber | undefined): number | null =>
  value === '' || value === undefined ? null : Number(value);

const getIntegerSchema = ({
  max,
  message,
  min
}: {
  max?: number;
  message: string;
  min: number;
}) => {
  const schema = number()
    .transform((value, originalValue) => (originalValue === '' ? null : value))
    .nullable()
    .typeError(message)
    .integer(message)
    .min(min, message);

  return max === undefined ? schema : schema.max(max, message);
};

const getEventHandlerArgsSchema = (t: TFunction) =>
  string()
    .test(
      'needs-an-event-handler',
      t(labelEventHandlerArgumentsNeedAnEventHandler),
      (value, { parent }) =>
        !value || !!(parent as DataProcessingValues).eventHandler
    )
    .test('forbidden-tokens', (value, { createError }) => {
      const invalidArguments = textToArguments(value ?? '').filter((argument) =>
        forbiddenArgumentTokens.some((token) => argument.includes(token))
      );

      return (
        invalidArguments.length === 0 ||
        createError({
          message: t(labelForbiddenEventHandlerArguments, {
            arguments: invalidArguments.join(', ')
          })
        })
      );
    });

const getTestId = (field: string): string =>
  `service-form-${field.replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`)}`;

const getNumberInput = ({
  fieldName,
  getDisabled,
  label,
  min,
  unit
}: {
  fieldName: keyof DataProcessingValues;
  getDisabled?: (values: FormValues) => boolean;
  label: string;
  min: number;
  unit?: string;
}): InputPropsWithoutGroup => ({
  dataTestId: getTestId(fieldName),
  fieldName: `dataProcessing.${fieldName}`,
  getDisabled,
  label,
  text: {
    ...(unit && { endAdornment: createElement('span', null, unit) }),
    min,
    type: 'number'
  },
  type: InputType.Text
});

const getTriStateInput = ({
  fieldName,
  getDisabled,
  label
}: {
  fieldName: keyof DataProcessingValues;
  getDisabled?: (values: FormValues) => boolean;
  label: string;
}): InputPropsWithoutGroup => ({
  dataTestId: getTestId(fieldName),
  fieldName: `dataProcessing.${fieldName}`,
  getDisabled,
  label,
  segmentedButtons: { options: triStateOptions },
  type: InputType.SegmentedButtons
});

const getBlock = ({
  name,
  title,
  columns
}: {
  columns: Array<InputPropsWithoutGroup>;
  name: string;
  title: string;
}): InputPropsWithoutGroup => ({
  additionalLabel: title,
  fieldName: `data-processing-${name}`,
  grid: { className: 'grid-cols-1', columns },
  label: `service-form-data-processing-${name}`,
  type: InputType.Grid
});

const getEventHandlerBlock = ({
  isCloudPlatform,
  t
}: {
  isCloudPlatform: boolean;
  t: TFunction;
}): InputPropsWithoutGroup =>
  getBlock({
    columns: [
      getTriStateInput({
        fieldName: 'eventHandlerEnabled',
        label: t(labelEventHandlerEnabled)
      }),
      {
        connectedAutocomplete: {
          additionalConditionParameters: [],
          baseEndpoint: servicesBaseEndpoint,
          customQueryParameters: [],
          decoder: namedEntitiesListDecoder,
          endpoint: serviceFormCommandsEndpoint,
          getOptionLabel: (option) => (option as SelectEntry)?.name,
          queryKey: 'service-form-event-handler',
          useNewAPIFormat: true
        },
        dataTestId: getTestId('eventHandler'),
        fieldName: 'dataProcessing.eventHandler',
        label: t(labelEventHandler),
        type: InputType.SingleConnectedAutocomplete
      },
      ...(isCloudPlatform
        ? []
        : [
            {
              dataTestId: getTestId('eventHandlerArgs'),
              fieldName: 'dataProcessing.eventHandlerArgs',
              label: t(labelArgs),
              text: { placeholder: '!arg1!arg2' },
              type: InputType.Text
            },
            // Legacy shows it at the top of the tab, alone with the
            // acknowledgement timeout; it runs the OCSP command after each
            // check, the closest block is this one.
            getTriStateInput({
              fieldName: 'obsessOverService',
              label: t(labelObsessOverService)
            })
          ])
    ],
    name: 'event-handler',
    title: t(labelEventHandler)
  });

const getFreshnessBlock = ({
  isCloudPlatform,
  t
}: {
  isCloudPlatform: boolean;
  t: TFunction;
}): InputPropsWithoutGroup =>
  getBlock({
    columns: [
      getTriStateInput({
        fieldName: 'checkFreshness',
        getDisabled: hasCentreonMonitoringAgentCommand,
        label: t(labelCheckFreshness)
      }),
      // 0 leaves the threshold to the engine.
      getNumberInput({
        fieldName: 'freshnessThreshold',
        getDisabled: hasCentreonMonitoringAgentCommand,
        label: t(labelFreshnessThreshold),
        min: 0,
        unit: t(labelSeconds)
      }),
      ...(isCloudPlatform
        ? []
        : [
            getNumberInput({
              fieldName: 'acknowledgmentTimeout',
              label: t(labelAcknowledgementTimeout),
              min: 1
            })
          ])
    ],
    name: 'freshness',
    title: t(labelFreshnessControlOptions)
  });

const getFlappingBlock = (t: TFunction): InputPropsWithoutGroup =>
  getBlock({
    columns: [
      getTriStateInput({
        fieldName: 'flapDetectionEnabled',
        label: t(labelFlapDetectionEnabled)
      }),
      // No cross-check between the two thresholds, as legacy.
      getNumberInput({
        fieldName: 'lowFlapThreshold',
        label: t(labelLowFlapThreshold),
        min: 0,
        unit: '%'
      }),
      getNumberInput({
        fieldName: 'highFlapThreshold',
        label: t(labelHighFlapThreshold),
        min: 0,
        unit: '%'
      })
    ],
    name: 'flapping',
    title: t(labelFlappingOptions)
  });

const getHistoryBlock = (t: TFunction): InputPropsWithoutGroup =>
  getBlock({
    columns: [
      getTriStateInput({
        fieldName: 'retainStatusInformation',
        label: t(labelRetainStatusInformation)
      }),
      getTriStateInput({
        fieldName: 'retainNonStatusInformation',
        label: t(labelRetainNonStatusInformation)
      }),
      {
        additionalLabel: t(labelStalkingOptions),
        additionalLabelClassName: 'text-sm font-normal',
        fieldName: 'data-processing-stalking-options',
        grid: {
          className: 'grid-cols-4',
          columns: stalkingOptions.map(({ label, letter }) => ({
            dataTestId: `${getTestId('stalkingOptions')}-${letter}`,
            fieldName: `dataProcessing.stalkingOptions.${letter}`,
            label: t(label),
            type: InputType.Checkbox
          }))
        },
        label: getTestId('stalkingOptions'),
        type: InputType.Grid
      }
    ],
    name: 'history',
    title: t(labelHistoryOptions)
  });

// The legacy "Data Processing" tab (F28–F40).
export const dataProcessing: FormSection<DataProcessingDetail> = {
  defaultValues: { dataProcessing: defaultDataProcessing },
  detailDecoders: {
    dataProcessing: JsonDecoder.optional(
      JsonDecoder.nullable(dataProcessingDecoder)
    ).map((value) => value ?? defaultDataProcessing)
  },
  detailKeyMap: { dataProcessing: 'data_processing' },
  getInputs: ({ isCloudPlatform, t }) => {
    const eventHandler = getEventHandlerBlock({ isCloudPlatform, t });
    const freshness = getFreshnessBlock({ isCloudPlatform, t });

    return [
      {
        fieldName: 'data-processing-layout',
        grid: {
          className: isCloudPlatform
            ? 'grid-cols-1 gap-x-8 @[800px]:grid-cols-2'
            : 'grid-cols-1 gap-x-8 @[800px]:grid-cols-2 @[1100px]:grid-cols-4',
          columns: isCloudPlatform
            ? [eventHandler, freshness]
            : [eventHandler, freshness, getFlappingBlock(t), getHistoryBlock(t)]
        },
        label: 'service-form-data-processing-layout',
        type: InputType.Grid
      }
    ];
  },
  getSchema: ({ isCloudPlatform, t }) => ({
    dataProcessing: object({
      freshnessThreshold: getIntegerSchema({
        message: t(labelMustBePositiveIntegerOrZero),
        min: 0
      }),
      ...(!isCloudPlatform && {
        acknowledgmentTimeout: getIntegerSchema({
          message: t(labelMustBeIntegerOfAtLeastOne),
          min: 1
        }),
        eventHandlerArgs: getEventHandlerArgsSchema(t),
        highFlapThreshold: getIntegerSchema({
          max: 100,
          message: t(labelMustBeIntegerBetweenZeroAndOneHundred),
          min: 0
        }),
        lowFlapThreshold: getIntegerSchema({
          max: 100,
          message: t(labelMustBeIntegerBetweenZeroAndOneHundred),
          min: 0
        })
      })
    })
  }),
  label: labelDataProcessing,
  toPayload: (values, { isCloudPlatform }) => {
    const dataProcessingValues = (values.dataProcessing ??
      defaultDataProcessing) as DataProcessingValues;
    const isCentreonMonitoringAgent = hasCentreonMonitoringAgentCommand(values);

    return {
      data_processing: {
        check_freshness: isCentreonMonitoringAgent
          ? 'true'
          : dataProcessingValues.checkFreshness,
        event_handler_command_id: dataProcessingValues.eventHandler?.id ?? null,
        event_handler_enabled: dataProcessingValues.eventHandlerEnabled,
        freshness_threshold: isCentreonMonitoringAgent
          ? centreonMonitoringAgentFreshnessThreshold
          : toApiNumber(dataProcessingValues.freshnessThreshold),
        // Refused on cloud.
        ...(!isCloudPlatform && {
          acknowledgment_timeout: toApiNumber(
            dataProcessingValues.acknowledgmentTimeout
          ),
          event_handler_args: textToArguments(
            dataProcessingValues.eventHandlerArgs
          ),
          flap_detection_enabled: dataProcessingValues.flapDetectionEnabled,
          high_flap_threshold: toApiNumber(
            dataProcessingValues.highFlapThreshold
          ),
          low_flap_threshold: toApiNumber(
            dataProcessingValues.lowFlapThreshold
          ),
          obsess_over_service: dataProcessingValues.obsessOverService,
          retain_non_status_information:
            dataProcessingValues.retainNonStatusInformation,
          retain_status_information:
            dataProcessingValues.retainStatusInformation,
          stalking_options: stalkingOptions
            .map(({ letter }) => letter)
            .filter(
              (letter) =>
                dataProcessingValues.stalkingOptions?.[letter]?.checked
            )
        })
      }
    };
  }
};
