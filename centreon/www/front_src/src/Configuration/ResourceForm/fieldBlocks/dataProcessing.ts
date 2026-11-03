import { InputType, type SelectEntry } from '@centreon/ui';

import type { TFunction } from 'i18next';
import { createElement } from 'react';
import { JsonDecoder } from 'ts.data.json';
import { type ObjectShape, object, string } from 'yup';

import { optionalNamedEntityDecoder } from '../api/decoders';
import { argumentsToText, textToArguments } from '../commandArguments';
import { getBlock, threeColumnsFromWide } from '../grid';
import type {
  FieldBlock,
  FormValues,
  NamedEntity,
  ResourceOptions
} from '../models';
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
  labelCritical,
  labelDataProcessing,
  labelEnableFreshnessCheck,
  labelEventHandler,
  labelEventHandlerArgumentsNeedAnEventHandler,
  labelEventHandlerEnabled,
  labelFlapDetection,
  labelFlapDetectionEnabled,
  labelForbiddenEventHandlerArguments,
  labelFreshnessCheck,
  labelFreshnessThreshold,
  labelHighFlapThreshold,
  labelLowFlapThreshold,
  labelMiscellaneous,
  labelMustBeIntegerOfAtLeastOne,
  labelMustBePositiveIntegerOrZero,
  labelObsessOverService,
  labelOk,
  labelRetainNonStatusInformation,
  labelRetainStatusInformation,
  labelSeconds,
  labelStalkingOptions,
  labelTimesSixtySeconds,
  labelUnknown,
  labelWarning
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
  getDisabled,
  idPrefix,
  isLabelShown = false,
  label
}: {
  fieldName: string;
  getDisabled?: (values: FormValues) => boolean;
  idPrefix: string;
  // Hidden when the block title already names the field.
  isLabelShown?: boolean;
  label: string;
}) => ({
  ...getInputIds(idPrefix, fieldName),
  ...(getDisabled && { getDisabled }),
  label,
  segmentedButtons: { hideLabel: !isLabelShown, options: triStateOptions },
  type: InputType.SegmentedButtons
});

const getNumberInput = ({
  fieldName,
  getDisabled,
  idPrefix,
  label,
  min,
  unit
}: {
  fieldName: string;
  getDisabled?: (values: FormValues) => boolean;
  idPrefix: string;
  label: string;
  min: number;
  unit?: string;
}) => ({
  ...getInputIds(idPrefix, fieldName),
  ...(getDisabled && { getDisabled }),
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

// What the engine reads as a line break, a tab or a carriage return.
const forbiddenArgumentTokens = ['#BR#', '#T#', '#R#'];

// Arguments wait for an event handler, and none may hold what the engine would
// read as a line break, a tab or a carriage return. `!` cannot end up inside
// one: it is the separator.
const getEventHandlerArgumentsSchema = (t: TFunction) =>
  string()
    .test(
      'needs-an-event-handler',
      t(labelEventHandlerArgumentsNeedAnEventHandler),
      (value, { parent }) =>
        !value || !!(parent as EventHandlerValues).eventHandler
    )
    .test('has-no-forbidden-token', (value, { createError }) => {
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

// The command the engine runs on a state change. Its arguments are refused on
// cloud.
export const getEventHandlerBlock = ({
  commandsEndpoint,
  resource: { baseEndpoint, idPrefix },
  withArgumentsRules = false
}: {
  commandsEndpoint: string;
  resource: ResourceOptions;
  // Checks the arguments as `getEventHandlerArgumentsSchema` says, where the
  // API refuses them otherwise.
  withArgumentsRules?: boolean;
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
  getSchema: ({ isCloudPlatform, t }): ObjectShape =>
    withArgumentsRules && !isCloudPlatform
      ? {
          dataProcessing: object({
            eventHandlerArgs: getEventHandlerArgumentsSchema(t)
          })
        }
      : {},
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

// The threshold of a forced freshness check
// (`DataProcessing::CENTREON_MONITORING_AGENT_FRESHNESS_THRESHOLD`).
const forcedFreshnessThreshold = 500;

// Whether the engine checks the results are recent enough, and how recent.
export const getFreshnessBlock = ({
  isForced,
  resource: { idPrefix }
}: {
  // When it holds, as for a Centreon Monitoring Agent check command, the
  // check is on with a 500 s threshold whatever the fields say: they are
  // disabled and the payload sends those values.
  isForced?: (values: FormValues) => boolean;
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
          getDisabled: isForced,
          idPrefix,
          label: t(labelEnableFreshnessCheck)
        }),
        // 0 leaves the threshold to the engine.
        getNumberInput({
          fieldName: 'freshnessThreshold',
          getDisabled: isForced,
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
    if (isForced?.(values)) {
      return {
        data_processing: {
          check_freshness: 'true',
          freshness_threshold: forcedFreshnessThreshold
        }
      };
    }

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
export const getAcknowledgmentTimeoutBlock = ({
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
export const getFlappingBlock = ({
  maxThreshold,
  resource: { idPrefix }
}: {
  // The highest threshold the form takes, and the untranslated message for
  // one out of 0–max. Without it the server alone enforces a cap.
  maxThreshold?: { max: number; message: string };
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
    // No cross-check between the two thresholds, as legacy.
    const positiveOrZero = () =>
      maxThreshold
        ? getIntegerSchema({
            message: t(maxThreshold.message),
            min: 0
          }).max(maxThreshold.max, t(maxThreshold.message))
        : getIntegerSchema({
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

// In the order the API normalizes them.
const stalkingOptions = [
  { label: labelOk, letter: 'o' },
  { label: labelWarning, letter: 'w' },
  { label: labelUnknown, letter: 'u' },
  { label: labelCritical, letter: 'c' }
] as const;

type StalkingOption = (typeof stalkingOptions)[number]['letter'];

// One checkbox input each, as `@centreon/ui` stores them: `CheckboxGroup`
// empties its field once disabled, so read-only would show none checked.
type StalkingOptionsValues = Record<StalkingOption, { checked: boolean }>;

const toStalkingOptionsValues = (
  letters: Array<string>
): StalkingOptionsValues =>
  Object.fromEntries(
    stalkingOptions.map(({ letter }) => [
      letter,
      { checked: letters.includes(letter) }
    ])
  ) as StalkingOptionsValues;

interface ObsessRetainAndStalkingValues {
  obsessOverService: TriState;
  retainNonStatusInformation: TriState;
  retainStatusInformation: TriState;
  stalkingOptions: StalkingOptionsValues;
}

const defaultObsessRetainAndStalking: ObsessRetainAndStalkingValues = {
  obsessOverService: defaultTriState,
  retainNonStatusInformation: defaultTriState,
  retainStatusInformation: defaultTriState,
  stalkingOptions: toStalkingOptionsValues([])
};

// Whether the engine runs the OCSP command after each check, keeps the state
// and the other information over a restart, and which states log every change
// of the plugin output. On-premise only.
export const getObsessRetainAndStalkingBlock = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<DataProcessingDetail<ObsessRetainAndStalkingValues>> => ({
  defaultValues: { dataProcessing: defaultObsessRetainAndStalking },
  detailDecoders: getDataProcessingDecoder<ObsessRetainAndStalkingValues>({
    decoders: {
      obsessOverService: optionalTriStateDecoder,
      retainNonStatusInformation: optionalTriStateDecoder,
      retainStatusInformation: optionalTriStateDecoder,
      stalkingOptions: JsonDecoder.optional(
        JsonDecoder.nullable(
          JsonDecoder.array(JsonDecoder.string, 'Stalking options')
        )
      ).map((value) => toStalkingOptionsValues(value ?? []))
    },
    defaultValues: defaultObsessRetainAndStalking,
    keyMap: {
      obsessOverService: 'obsess_over_service',
      retainNonStatusInformation: 'retain_non_status_information',
      retainStatusInformation: 'retain_status_information',
      stalkingOptions: 'stalking_options'
    }
  }),
  detailKeyMap,
  getInputs: ({ t }) => ({
    obsessRetainAndStalking: getBlock({
      columns: [
        getBlock({
          className: threeColumnsFromWide,
          columns: [
            getTriStateInput({
              fieldName: 'obsessOverService',
              idPrefix,
              isLabelShown: true,
              label: t(labelObsessOverService)
            }),
            getTriStateInput({
              fieldName: 'retainStatusInformation',
              idPrefix,
              isLabelShown: true,
              label: t(labelRetainStatusInformation)
            }),
            getTriStateInput({
              fieldName: 'retainNonStatusInformation',
              idPrefix,
              isLabelShown: true,
              label: t(labelRetainNonStatusInformation)
            })
          ],
          idPrefix,
          name: 'data-processing-obsess-and-retain'
        }),
        getBlock({
          className: 'grid-cols-2 @[600px]:grid-cols-4',
          columns: stalkingOptions.map(({ label, letter }) => ({
            dataTestId: `${idPrefix}-data-processing-stalkingOptions-${letter}`,
            fieldName: `dataProcessing.stalkingOptions.${letter}`,
            label: t(label),
            type: InputType.Checkbox
          })),
          idPrefix,
          name: 'data-processing-stalking-options',
          title: t(labelStalkingOptions)
        })
      ],
      idPrefix,
      name: 'data-processing-obsess-retain-and-stalking',
      title: t(labelMiscellaneous)
    })
  }),
  getSchema: () => ({}),
  isAvailable: ({ isCloudPlatform }) => !isCloudPlatform,
  toPayload: (values) => {
    const dataProcessing = getDataProcessing(
      values,
      defaultObsessRetainAndStalking
    );

    return {
      data_processing: {
        obsess_over_service: dataProcessing.obsessOverService,
        retain_non_status_information:
          dataProcessing.retainNonStatusInformation,
        retain_status_information: dataProcessing.retainStatusInformation,
        // Deduplicated and in the API order by construction.
        stalking_options: stalkingOptions
          .map(({ letter }) => letter)
          .filter((letter) => dataProcessing.stalkingOptions?.[letter]?.checked)
      }
    };
  }
});
