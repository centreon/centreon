import { InputType, type SelectEntry } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { object } from 'yup';

import { optionalNamedEntityDecoder } from '../api/decoders';
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
  labelActiveChecksEnabled,
  labelCheckPeriod,
  labelIsVolatile,
  labelMaxCheckAttempts,
  labelMustBeIntegerOfAtLeastOne,
  labelNormalCheckInterval,
  labelPassiveChecksEnabled,
  labelRetryCheckInterval
} from '../translatedLabels';
import {
  defaultTriState,
  optionalTriStateDecoder,
  type TriState,
  triStateOptions
} from '../triState';

// Both blocks fill the API's `scheduling_options`.

interface CheckPeriodAndIntervalsValues {
  checkPeriod: NamedEntity | null;
  maxCheckAttempts: OptionalNumber;
  normalCheckInterval: OptionalNumber;
  retryCheckInterval: OptionalNumber;
}

interface ActiveAndPassiveChecksValues {
  activeCheckEnabled: TriState;
  // Only where the block is built `withVolatile`.
  isVolatile?: TriState;
  passiveCheckEnabled: TriState;
}

const defaultCheckPeriodAndIntervals: CheckPeriodAndIntervalsValues = {
  checkPeriod: null,
  maxCheckAttempts: '',
  normalCheckInterval: '',
  retryCheckInterval: ''
};

const defaultActiveAndPassiveChecks: ActiveAndPassiveChecksValues = {
  activeCheckEnabled: defaultTriState,
  passiveCheckEnabled: defaultTriState
};

const getInputIds = (idPrefix: string, fieldName: string) => ({
  dataTestId: `${idPrefix}-scheduling-options-${fieldName}`,
  fieldName: `schedulingOptions.${fieldName}`
});

// The check period and the counts and intervals the engine schedules with.
export const getCheckPeriodAndIntervalsBlock = ({
  resource: { baseEndpoint, idPrefix },
  timePeriodsEndpoint
}: {
  resource: ResourceOptions;
  timePeriodsEndpoint: string;
}): FieldBlock<{ schedulingOptions: CheckPeriodAndIntervalsValues }> => ({
  defaultValues: { schedulingOptions: defaultCheckPeriodAndIntervals },
  detailDecoders: {
    schedulingOptions: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object<CheckPeriodAndIntervalsValues>(
          {
            checkPeriod: optionalNamedEntityDecoder('Check period'),
            maxCheckAttempts: optionalNumberDecoder,
            normalCheckInterval: optionalNumberDecoder,
            retryCheckInterval: optionalNumberDecoder
          },
          'Scheduling options',
          {
            checkPeriod: 'check_period',
            maxCheckAttempts: 'max_check_attempts',
            normalCheckInterval: 'normal_check_interval',
            retryCheckInterval: 'retry_check_interval'
          }
        )
      )
    ).map((value) => value ?? defaultCheckPeriodAndIntervals)
  },
  detailKeyMap: { schedulingOptions: 'scheduling_options' },
  getInputs: ({ t }) => {
    const getNumberInput = (
      fieldName: keyof CheckPeriodAndIntervalsValues,
      label: string
    ) => ({
      ...getInputIds(idPrefix, fieldName),
      label: t(label),
      text: { min: 1, type: 'number' },
      type: InputType.Text
    });

    return {
      checkPeriodAndIntervals: getBlock({
        columns: [
          {
            connectedAutocomplete: buildSelector({
              baseEndpoint,
              endpoint: timePeriodsEndpoint,
              getOptionLabel: (option) => (option as SelectEntry)?.name,
              queryKey: `${idPrefix}-check-period`
            }),
            ...getInputIds(idPrefix, 'checkPeriod'),
            label: t(labelCheckPeriod),
            type: InputType.SingleConnectedAutocomplete
          },
          getNumberInput('maxCheckAttempts', labelMaxCheckAttempts),
          getNumberInput('normalCheckInterval', labelNormalCheckInterval),
          getNumberInput('retryCheckInterval', labelRetryCheckInterval)
        ],
        idPrefix,
        name: 'scheduling-check-numbers'
      })
    };
  },
  getSchema: ({ t }) => {
    const atLeastOne = () =>
      getIntegerSchema({ message: t(labelMustBeIntegerOfAtLeastOne), min: 1 });

    return {
      schedulingOptions: object({
        maxCheckAttempts: atLeastOne(),
        normalCheckInterval: atLeastOne(),
        retryCheckInterval: atLeastOne()
      })
    };
  },
  toPayload: (values) => {
    const { schedulingOptions = defaultCheckPeriodAndIntervals } = values as {
      schedulingOptions?: CheckPeriodAndIntervalsValues;
    };

    return {
      scheduling_options: {
        check_timeperiod_id: schedulingOptions.checkPeriod?.id ?? null,
        max_check_attempts: toApiNumber(schedulingOptions.maxCheckAttempts),
        normal_check_interval: toApiNumber(
          schedulingOptions.normalCheckInterval
        ),
        retry_check_interval: toApiNumber(schedulingOptions.retryCheckInterval)
      }
    };
  }
});

// Whether the engine runs the checks, and whether it accepts submitted ones.
// `withVolatile` adds whether each non-OK result counts as a new problem, which
// only a service has. On premise only: cloud refuses all of them.
export const getActiveAndPassiveChecksBlock = ({
  resource: { idPrefix },
  withVolatile = false
}: {
  resource: ResourceOptions;
  withVolatile?: boolean;
}): FieldBlock<{ schedulingOptions: ActiveAndPassiveChecksValues }> => {
  const defaultValues: ActiveAndPassiveChecksValues = {
    ...defaultActiveAndPassiveChecks,
    ...(withVolatile && { isVolatile: defaultTriState })
  };

  return {
    defaultValues: { schedulingOptions: defaultValues },
    detailDecoders: {
      schedulingOptions: JsonDecoder.optional(
        JsonDecoder.nullable(
          JsonDecoder.object<ActiveAndPassiveChecksValues>(
            // Without it, no `isVolatile` key at all: a host never has one.
            {
              activeCheckEnabled: optionalTriStateDecoder,
              passiveCheckEnabled: optionalTriStateDecoder,
              ...(withVolatile && { isVolatile: optionalTriStateDecoder })
            } as JsonDecoder.DecoderObject<ActiveAndPassiveChecksValues>,
            'Scheduling options',
            {
              activeCheckEnabled: 'active_check_enabled',
              passiveCheckEnabled: 'passive_check_enabled',
              ...(withVolatile && { isVolatile: 'is_volatile' })
            }
          )
        )
      ).map((value) => value ?? defaultValues)
    },
    detailKeyMap: { schedulingOptions: 'scheduling_options' },
    getInputs: ({ isCloudPlatform, t }) => {
      if (isCloudPlatform) {
        return {};
      }

      const getTriStateInput = (
        fieldName: keyof ActiveAndPassiveChecksValues,
        label: string
      ) => ({
        ...getInputIds(idPrefix, fieldName),
        label: t(label),
        segmentedButtons: { options: triStateOptions },
        type: InputType.SegmentedButtons
      });

      return {
        activeAndPassiveChecks: getBlock({
          columns: [
            getTriStateInput('activeCheckEnabled', labelActiveChecksEnabled),
            getTriStateInput('passiveCheckEnabled', labelPassiveChecksEnabled),
            ...(withVolatile
              ? [getTriStateInput('isVolatile', labelIsVolatile)]
              : [])
          ],
          idPrefix,
          name: 'scheduling-checks-enabled'
        })
      };
    },
    getSchema: () => ({}),
    toPayload: (values, { isCloudPlatform }) => {
      if (isCloudPlatform) {
        return {};
      }

      const { schedulingOptions = defaultValues } = values as {
        schedulingOptions?: ActiveAndPassiveChecksValues;
      };

      return {
        scheduling_options: {
          active_check_enabled: schedulingOptions.activeCheckEnabled,
          passive_check_enabled: schedulingOptions.passiveCheckEnabled,
          ...(withVolatile && {
            is_volatile: schedulingOptions.isVolatile ?? defaultTriState
          })
        }
      };
    }
  };
};
