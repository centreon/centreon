import {
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import { createElement } from 'react';
import { JsonDecoder } from 'ts.data.json';
import { number, object } from 'yup';

import { commandsEndpoint } from '../../api/endpoints';
import { namedEntityDecoder } from '../../api/namedEntityDecoders';
import type { NamedEntity } from '../../models';
import {
  labelAcknowledgementTimeout,
  labelCheckFreshness,
  labelDataProcessing,
  labelEventHandler,
  labelEventHandlerArguments,
  labelEventHandlerEnabled,
  labelFlapDetectionEnabled,
  labelFlappingOptions,
  labelFreshnessControlOptions,
  labelFreshnessThreshold,
  labelHighFlapThreshold,
  labelLowFlapThreshold,
  labelMustBeIntegerOfAtLeastOne,
  labelMustBePositiveIntegerOrZero,
  labelSeconds
} from '../../translatedLabels';
import { buildSelector } from '../selector';
import {
  defaultTriState,
  type TriState,
  triStateDecoder,
  triStateOptions
} from '../triState';
import type { FormSection } from './models';

// A number field holds `''` until something is typed.
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
}

interface DataProcessingDetail {
  dataProcessing: DataProcessingValues;
}

const defaultDataProcessing: DataProcessingValues = {
  acknowledgmentTimeout: '',
  checkFreshness: defaultTriState,
  eventHandler: null,
  eventHandlerArgs: '',
  eventHandlerEnabled: defaultTriState,
  flapDetectionEnabled: defaultTriState,
  freshnessThreshold: '',
  highFlapThreshold: '',
  lowFlapThreshold: ''
};

// The way the server stores them: each argument behind a `!`.
const argumentsToText = (args: Array<string>): string =>
  args.map((argument) => `!${argument}`).join('');

// The leading `!` is optional, so `a!b` and `!a!b` are the same two arguments.
const textToArguments = (text: string): Array<string> => {
  if (text === '') {
    return [];
  }

  const [first, ...rest] = text.split('!');

  return first === '' ? rest : [first, ...rest];
};

// The detail endpoint leaves unset values out rather than sending null.
const optionalNumberDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(JsonDecoder.number)
).map((value): OptionalNumber => value ?? '');

// Left out on cloud for the onPrem-only toggles.
const optionalTriStateDecoder = JsonDecoder.optional(
  JsonDecoder.nullable(triStateDecoder)
).map((value) => value ?? defaultTriState);

const dataProcessingDecoder = JsonDecoder.object<DataProcessingValues>(
  {
    acknowledgmentTimeout: optionalNumberDecoder,
    checkFreshness: optionalTriStateDecoder,
    eventHandler: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object(namedEntityDecoder, 'Event handler')
      )
    ).map((value) => value ?? null),
    eventHandlerArgs: JsonDecoder.optional(
      JsonDecoder.array(JsonDecoder.string, 'Event handler arguments')
    ).map((value) => argumentsToText(value ?? [])),
    eventHandlerEnabled: optionalTriStateDecoder,
    flapDetectionEnabled: optionalTriStateDecoder,
    freshnessThreshold: optionalNumberDecoder,
    highFlapThreshold: optionalNumberDecoder,
    lowFlapThreshold: optionalNumberDecoder
  },
  'Data processing',
  {
    // The API spells it without the legacy `e`.
    acknowledgmentTimeout: 'acknowledgment_timeout',
    checkFreshness: 'check_freshness',
    eventHandler: 'event_handler',
    eventHandlerArgs: 'event_handler_args',
    eventHandlerEnabled: 'event_handler_enabled',
    flapDetectionEnabled: 'flap_detection_enabled',
    freshnessThreshold: 'freshness_threshold',
    highFlapThreshold: 'high_flap_threshold',
    lowFlapThreshold: 'low_flap_threshold'
  }
);

const toApiNumber = (value: OptionalNumber | undefined): number | null =>
  value === '' || value === undefined ? null : Number(value);

const getIntegerSchema = ({ message, min }: { message: string; min: number }) =>
  number()
    .transform((value, originalValue) => (originalValue === '' ? null : value))
    .nullable()
    .typeError(message)
    .integer(message)
    .min(min, message);

const getNumberInput = ({
  fieldName,
  label,
  min,
  unit
}: {
  fieldName: keyof DataProcessingValues;
  label: string;
  min: number;
  unit?: string;
}) => ({
  dataTestId: `host-form-data-processing-${fieldName}`,
  fieldName: `dataProcessing.${fieldName}`,
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
  label
}: {
  fieldName: keyof DataProcessingValues;
  label: string;
}) => ({
  dataTestId: `host-form-data-processing-${fieldName}`,
  fieldName: `dataProcessing.${fieldName}`,
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
  // Rendered as the block's title by the grid around it.
  additionalLabel: title,
  fieldName: `data-processing-${name}`,
  grid: { className: 'grid-cols-1', columns },
  label: `host-form-data-processing-${name}`,
  type: InputType.Grid
});

export const dataProcessing: FormSection<DataProcessingDetail> = {
  defaultValues: { dataProcessing: defaultDataProcessing },
  detailDecoders: {
    dataProcessing: JsonDecoder.optional(
      JsonDecoder.nullable(dataProcessingDecoder)
    ).map((value) => value ?? defaultDataProcessing)
  },
  detailKeyMap: { dataProcessing: 'data_processing' },
  getInputs: ({ isCloudPlatform, t }) => {
    const eventHandler = getBlock({
      columns: [
        getTriStateInput({
          fieldName: 'eventHandlerEnabled',
          label: t(labelEventHandlerEnabled)
        }),
        {
          connectedAutocomplete: buildSelector({
            // Legacy offers every active command, whatever its type.
            customQueryParameters: [{ name: 'is_activated', value: true }],
            endpoint: commandsEndpoint,
            getOptionLabel: (option) => (option as SelectEntry)?.name,
            queryKey: 'host-form-event-handler'
          }),
          dataTestId: 'host-form-data-processing-eventHandler',
          fieldName: 'dataProcessing.eventHandler',
          label: t(labelEventHandler),
          type: InputType.SingleConnectedAutocomplete
        },
        ...(isCloudPlatform
          ? []
          : [
              {
                dataTestId: 'host-form-data-processing-eventHandlerArgs',
                fieldName: 'dataProcessing.eventHandlerArgs',
                label: t(labelEventHandlerArguments),
                text: { placeholder: '!arg1!arg2' },
                type: InputType.Text
              }
            ])
      ],
      name: 'event-handler',
      title: t(labelEventHandler)
    });

    const freshness = getBlock({
      columns: [
        getTriStateInput({
          fieldName: 'checkFreshness',
          label: t(labelCheckFreshness)
        }),
        // 0 leaves the threshold to the engine.
        getNumberInput({
          fieldName: 'freshnessThreshold',
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

    const flapping = getBlock({
      columns: [
        getTriStateInput({
          fieldName: 'flapDetectionEnabled',
          label: t(labelFlapDetectionEnabled)
        }),
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

    // Flapping is an onPrem concern as a whole, so cloud has two blocks.
    return [
      {
        fieldName: 'data-processing-layout',
        grid: {
          className: isCloudPlatform
            ? 'grid-cols-1 gap-x-8 @[800px]:grid-cols-2'
            : 'grid-cols-1 gap-x-8 @[800px]:grid-cols-2 @[1100px]:grid-cols-3',
          columns: isCloudPlatform
            ? [eventHandler, freshness]
            : [eventHandler, freshness, flapping]
        },
        label: 'host-form-data-processing-layout',
        type: InputType.Grid
      }
    ];
  },
  getSchema: ({ t }) => ({
    dataProcessing: object({
      acknowledgmentTimeout: getIntegerSchema({
        message: t(labelMustBeIntegerOfAtLeastOne),
        min: 1
      }),
      freshnessThreshold: getIntegerSchema({
        message: t(labelMustBePositiveIntegerOrZero),
        min: 0
      }),
      // The server refuses anything above 100; no translated message says so.
      highFlapThreshold: getIntegerSchema({
        message: t(labelMustBePositiveIntegerOrZero),
        min: 0
      }),
      lowFlapThreshold: getIntegerSchema({
        message: t(labelMustBePositiveIntegerOrZero),
        min: 0
      })
    })
  }),
  label: labelDataProcessing,
  toPayload: (values, { isCloudPlatform }) => {
    const dataProcessingValues = (values.dataProcessing ??
      defaultDataProcessing) as DataProcessingValues;

    return {
      data_processing: {
        check_freshness: dataProcessingValues.checkFreshness,
        event_handler_command_id: dataProcessingValues.eventHandler?.id ?? null,
        event_handler_enabled: dataProcessingValues.eventHandlerEnabled,
        freshness_threshold: toApiNumber(
          dataProcessingValues.freshnessThreshold
        ),
        // Refused on cloud, where the server keeps them unset.
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
          low_flap_threshold: toApiNumber(dataProcessingValues.lowFlapThreshold)
        })
      }
    };
  }
};
