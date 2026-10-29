import { InputType, type SelectEntry } from '@centreon/ui';

import { createElement } from 'react';
import { JsonDecoder } from 'ts.data.json';
import { object } from 'yup';

import { optionalNamedEntityDecoder } from '../api/decoders';
import { argumentsToText, textToArguments } from '../commandArguments';
import { getBlock } from '../grid';
import type { FieldBlock, NamedEntity, ResourceOptions } from '../models';
import {
  getIntegerSchema,
  type OptionalNumber,
  optionalNumberDecoder,
  toApiNumber
} from '../numbers';
import { buildSelector } from '../selector';
import {
  labelAcknowledgementTimeout,
  labelArgs,
  labelArguments,
  labelAutoRemediation,
  labelCommand,
  labelDataProcessing,
  labelEnableFreshnessCheck,
  labelEventHandler,
  labelEventHandlerEnabled,
  labelFlapDetection,
  labelFlapDetectionEnabled,
  labelFreshnessCheck,
  labelFreshnessThreshold,
  labelHighFlapThreshold,
  labelLowFlapThreshold,
  labelMustBeIntegerOfAtLeastOne,
  labelMustBePositiveIntegerOrZero,
  labelSeconds,
  labelTimesSixtySeconds
} from '../translatedLabels';
import {
  defaultTriState,
  optionalTriStateDecoder,
  type TriState,
  triStateOptions
} from '../triState';

// The four blocks fill the form's `dataProcessing` and the API's
// `data_processing`.
interface DataProcessingDetail<Values> {
  dataProcessing: Values;
}

const getDataProcessingDecoder = <Values>({
  decoders,
  defaultValues,
  keyMap
}: {
  decoders: JsonDecoder.DecoderObject<Values>;
  defaultValues: Values;
  keyMap: JsonDecoder.DecoderObjectKeyMap<Values>;
}): JsonDecoder.DecoderObject<DataProcessingDetail<Values>> =>
  ({
    dataProcessing: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object<Values>(decoders, 'Data processing', keyMap)
      )
    ).map((value) => value ?? defaultValues)
  }) as JsonDecoder.DecoderObject<DataProcessingDetail<Values>>;

const detailKeyMap = { dataProcessing: 'data_processing' } as const;

const getDataProcessing = <Values>(
  values: Record<string, unknown>,
  defaultValues: Values
): Values => (values.dataProcessing ?? defaultValues) as Values;

const getInputIds = (idPrefix: string, fieldName: string) => ({
  dataTestId: `${idPrefix}-data-processing-${fieldName}`,
  fieldName: `dataProcessing.${fieldName}`
});

const getTriStateInput = ({
  fieldName,
  idPrefix,
  label
}: {
  fieldName: string;
  idPrefix: string;
  label: string;
}) => ({
  ...getInputIds(idPrefix, fieldName),
  label,
  segmentedButtons: { hideLabel: true, options: triStateOptions },
  type: InputType.SegmentedButtons
});

const getNumberInput = ({
  fieldName,
  idPrefix,
  label,
  min,
  unit
}: {
  fieldName: string;
  idPrefix: string;
  label: string;
  min: number;
  unit?: string;
}) => ({
  ...getInputIds(idPrefix, fieldName),
  label,
  text: {
    ...(unit && { endAdornment: createElement('span', null, unit) }),
    min,
    type: 'number'
  },
  type: InputType.Text
});

interface EventHandlerValues {
  eventHandler: NamedEntity | null;
  // Typed as legacy shows it, `!arg1!arg2`; the API takes the list.
  eventHandlerArgs: string;
  eventHandlerEnabled: TriState;
}

const defaultEventHandler: EventHandlerValues = {
  eventHandler: null,
  eventHandlerArgs: '',
  eventHandlerEnabled: defaultTriState
};

// The command the engine runs on a state change. Its arguments are refused on
// cloud.
export const eventHandler = ({
  commandsEndpoint,
  resource: { baseEndpoint, idPrefix }
}: {
  commandsEndpoint: string;
  resource: ResourceOptions;
}): FieldBlock<DataProcessingDetail<EventHandlerValues>> => ({
  defaultValues: { dataProcessing: defaultEventHandler },
  detailDecoders: getDataProcessingDecoder<EventHandlerValues>({
    decoders: {
      eventHandler: optionalNamedEntityDecoder('Event handler'),
      eventHandlerArgs: JsonDecoder.optional(
        JsonDecoder.array(JsonDecoder.string, 'Event handler arguments')
      ).map((value) => argumentsToText(value ?? [])),
      eventHandlerEnabled: optionalTriStateDecoder
    },
    defaultValues: defaultEventHandler,
    keyMap: {
      eventHandler: 'event_handler',
      eventHandlerArgs: 'event_handler_args',
      eventHandlerEnabled: 'event_handler_enabled'
    }
  }),
  detailKeyMap,
  getInputs: ({ isCloudPlatform, t }) => ({
    eventHandler: getBlock({
      columns: [
        getTriStateInput({
          fieldName: 'eventHandlerEnabled',
          idPrefix,
          label: t(labelEventHandlerEnabled)
        }),
        {
          additionalLabel: t(labelEventHandler),
          connectedAutocomplete: buildSelector({
            baseEndpoint,
            // Legacy offers every active command, whatever its type.
            customQueryParameters: [{ name: 'is_activated', value: true }],
            endpoint: commandsEndpoint,
            getOptionLabel: (option) => (option as SelectEntry)?.name,
            queryKey: `${idPrefix}-event-handler`
          }),
          ...getInputIds(idPrefix, 'eventHandler'),
          label: t(labelCommand),
          type: InputType.SingleConnectedAutocomplete
        },
        ...(isCloudPlatform
          ? []
          : [
              {
                additionalLabel: t(labelArguments),
                ...getInputIds(idPrefix, 'eventHandlerArgs'),
                label: t(labelArgs),
                text: { placeholder: '!arg1!arg2' },
                type: InputType.Text
              }
            ])
      ],
      idPrefix,
      name: 'data-processing-event-handler',
      title: t(labelAutoRemediation)
    })
  }),
  getSchema: () => ({}),
  toPayload: (values, { isCloudPlatform }) => {
    const dataProcessing = getDataProcessing(values, defaultEventHandler);

    return {
      data_processing: {
        event_handler_command_id: dataProcessing.eventHandler?.id ?? null,
        event_handler_enabled: dataProcessing.eventHandlerEnabled,
        ...(!isCloudPlatform && {
          event_handler_args: textToArguments(dataProcessing.eventHandlerArgs)
        })
      }
    };
  }
});

interface FreshnessValues {
  checkFreshness: TriState;
  freshnessThreshold: OptionalNumber;
}

const defaultFreshness: FreshnessValues = {
  checkFreshness: defaultTriState,
  freshnessThreshold: ''
};

// Whether the engine checks the results are recent enough, and how recent.
export const freshness = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<DataProcessingDetail<FreshnessValues>> => ({
  defaultValues: { dataProcessing: defaultFreshness },
  detailDecoders: getDataProcessingDecoder<FreshnessValues>({
    decoders: {
      checkFreshness: optionalTriStateDecoder,
      freshnessThreshold: optionalNumberDecoder
    },
    defaultValues: defaultFreshness,
    keyMap: {
      checkFreshness: 'check_freshness',
      freshnessThreshold: 'freshness_threshold'
    }
  }),
  detailKeyMap,
  getInputs: ({ t }) => ({
    freshness: getBlock({
      columns: [
        getTriStateInput({
          fieldName: 'checkFreshness',
          idPrefix,
          label: t(labelEnableFreshnessCheck)
        }),
        // 0 leaves the threshold to the engine.
        getNumberInput({
          fieldName: 'freshnessThreshold',
          idPrefix,
          label: t(labelFreshnessThreshold),
          min: 0,
          unit: t(labelSeconds)
        })
      ],
      idPrefix,
      name: 'data-processing-freshness',
      title: t(labelFreshnessCheck)
    })
  }),
  getSchema: ({ t }) => ({
    dataProcessing: object({
      freshnessThreshold: getIntegerSchema({
        message: t(labelMustBePositiveIntegerOrZero),
        min: 0
      })
    })
  }),
  toPayload: (values) => {
    const dataProcessing = getDataProcessing(values, defaultFreshness);

    return {
      data_processing: {
        check_freshness: dataProcessing.checkFreshness,
        freshness_threshold: toApiNumber(dataProcessing.freshnessThreshold)
      }
    };
  }
});

interface AcknowledgmentTimeoutValues {
  acknowledgmentTimeout: OptionalNumber;
}

const defaultAcknowledgmentTimeout: AcknowledgmentTimeoutValues = {
  acknowledgmentTimeout: ''
};

// How long an acknowledgement lasts. Refused on cloud.
export const acknowledgmentTimeout = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<DataProcessingDetail<AcknowledgmentTimeoutValues>> => ({
  defaultValues: { dataProcessing: defaultAcknowledgmentTimeout },
  detailDecoders: getDataProcessingDecoder<AcknowledgmentTimeoutValues>({
    decoders: { acknowledgmentTimeout: optionalNumberDecoder },
    defaultValues: defaultAcknowledgmentTimeout,
    // The API spells it without the legacy `e`.
    keyMap: { acknowledgmentTimeout: 'acknowledgment_timeout' }
  }),
  detailKeyMap,
  getInputs: ({ isCloudPlatform, t }) =>
    isCloudPlatform
      ? {}
      : {
          acknowledgmentTimeout: getBlock({
            columns: [
              getNumberInput({
                fieldName: 'acknowledgmentTimeout',
                idPrefix,
                label: t(labelAcknowledgementTimeout),
                min: 1,
                unit: t(labelTimesSixtySeconds)
              })
            ],
            idPrefix,
            name: 'data-processing-acknowledgment-timeout',
            title: t(labelDataProcessing)
          })
        },
  getSchema: ({ t }) => ({
    dataProcessing: object({
      acknowledgmentTimeout: getIntegerSchema({
        message: t(labelMustBeIntegerOfAtLeastOne),
        min: 1
      })
    })
  }),
  toPayload: (values, { isCloudPlatform }) =>
    isCloudPlatform
      ? {}
      : {
          data_processing: {
            acknowledgment_timeout: toApiNumber(
              getDataProcessing(values, defaultAcknowledgmentTimeout)
                .acknowledgmentTimeout
            )
          }
        }
});

interface FlappingValues {
  flapDetectionEnabled: TriState;
  highFlapThreshold: OptionalNumber;
  lowFlapThreshold: OptionalNumber;
}

const defaultFlapping: FlappingValues = {
  flapDetectionEnabled: defaultTriState,
  highFlapThreshold: '',
  lowFlapThreshold: ''
};

// Whether the engine detects a flapping state, and between which thresholds.
// Refused on cloud.
export const flapping = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<DataProcessingDetail<FlappingValues>> => ({
  defaultValues: { dataProcessing: defaultFlapping },
  detailDecoders: getDataProcessingDecoder<FlappingValues>({
    decoders: {
      flapDetectionEnabled: optionalTriStateDecoder,
      highFlapThreshold: optionalNumberDecoder,
      lowFlapThreshold: optionalNumberDecoder
    },
    defaultValues: defaultFlapping,
    keyMap: {
      flapDetectionEnabled: 'flap_detection_enabled',
      highFlapThreshold: 'high_flap_threshold',
      lowFlapThreshold: 'low_flap_threshold'
    }
  }),
  detailKeyMap,
  getInputs: ({ isCloudPlatform, t }) =>
    isCloudPlatform
      ? {}
      : {
          flapping: getBlock({
            columns: [
              getTriStateInput({
                fieldName: 'flapDetectionEnabled',
                idPrefix,
                label: t(labelFlapDetectionEnabled)
              }),
              getNumberInput({
                fieldName: 'lowFlapThreshold',
                idPrefix,
                label: t(labelLowFlapThreshold),
                min: 0,
                unit: '%'
              }),
              getNumberInput({
                fieldName: 'highFlapThreshold',
                idPrefix,
                label: t(labelHighFlapThreshold),
                min: 0,
                unit: '%'
              })
            ],
            idPrefix,
            name: 'data-processing-flapping',
            title: t(labelFlapDetection)
          })
        },
  getSchema: ({ t }) => {
    // No translated message for 0–100: the server enforces the cap.
    const positiveOrZero = () =>
      getIntegerSchema({
        message: t(labelMustBePositiveIntegerOrZero),
        min: 0
      });

    return {
      dataProcessing: object({
        highFlapThreshold: positiveOrZero(),
        lowFlapThreshold: positiveOrZero()
      })
    };
  },
  toPayload: (values, { isCloudPlatform }) => {
    if (isCloudPlatform) {
      return {};
    }

    const dataProcessing = getDataProcessing(values, defaultFlapping);

    return {
      data_processing: {
        flap_detection_enabled: dataProcessing.flapDetectionEnabled,
        high_flap_threshold: toApiNumber(dataProcessing.highFlapThreshold),
        low_flap_threshold: toApiNumber(dataProcessing.lowFlapThreshold)
      }
    };
  }
});
