import { InputType, type SelectEntry } from '@centreon/ui';

import type { FormikValues } from 'formik';
import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import {
  number,
  type ObjectShape,
  object,
  string,
  type TestContext
} from 'yup';

import { namedEntityDecoder } from '../api/decoders';
import { argumentsToText, textToArguments } from '../commandArguments';
import { getBlock } from '../grid';
import type {
  FieldBlock,
  FormValues,
  NamedEntity,
  ResourceOptions
} from '../models';
import { buildSelector } from '../selector';
import {
  labelArguments,
  labelArgumentsForbiddenTokens,
  labelCheckCommand,
  labelFillAllArgumentsOrNone,
  labelRequired
} from '../translatedLabels';

// The detail tells a Centreon Monitoring Agent command, whose freshness check
// the server forces. A command picked in the selector is never one.
type CheckCommand = NamedEntity & { isCentreonMonitoringAgent?: boolean };

interface CheckCommandValues {
  args: string;
  command: CheckCommand | null;
}

// Within the API's `check_options`, which other blocks fill too.
interface CheckCommandDetail {
  checkOptions: CheckCommandValues;
}

const defaultCheckCommand: CheckCommandValues = { args: '', command: null };

// What the server writes for a new line, a tab and a carriage return: an
// argument holding one would come back changed.
const forbiddenArgumentTokens = /#BR#|#T#|#R#/;

const checkCommandDecoder = JsonDecoder.object<CheckCommandValues>(
  {
    args: JsonDecoder.optional(
      JsonDecoder.array(JsonDecoder.string, 'Check command arguments')
    ).map((value) => argumentsToText(value ?? [])),
    command: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object<CheckCommand>(
          {
            ...namedEntityDecoder,
            isCentreonMonitoringAgent: JsonDecoder.optional(JsonDecoder.boolean)
          },
          'Check command',
          { isCentreonMonitoringAgent: 'is_centreon_monitoring_agent' }
        )
      )
    ).map((value) => value ?? null)
  },
  'Check options'
);

// The whole form's values, from a rule on one of its fields: the outermost
// ancestor.
const getFormValues = (context: TestContext): FormValues =>
  (context.from?.[context.from.length - 1]?.value ?? {}) as FormValues;

export interface CheckCommandOptions {
  // A command the resource runs without one of its own, as a service from its
  // template: arguments then go with that command, and none is required.
  hasInheritedCommand?: (values: FormValues) => boolean;
  // A command, its own or inherited, is required. Never in a mass change.
  isCommandRequired?: boolean;
  // Refuses arguments partly filled (legacy's rule) or holding the escape
  // tokens the server writes for line breaks and tabs.
  validateArguments?: boolean;
}

const getCheckCommandSchema = ({
  hasCommand,
  isCommandRequired,
  t,
  validateArguments
}: {
  hasCommand: (values: FormValues) => boolean;
  isCommandRequired: boolean;
  t: TFunction;
  validateArguments: boolean;
}): ObjectShape => {
  if (!isCommandRequired && !validateArguments) {
    return {};
  }

  return {
    checkOptions: object({
      ...(validateArguments && {
        args: string()
          .test(
            'no-escape-tokens',
            t(labelArgumentsForbiddenTokens),
            (value) => !forbiddenArgumentTokens.test(value ?? '')
          )
          // `!a!!c` misses its second argument.
          .test(
            'all-or-none',
            t(labelFillAllArgumentsOrNone),
            function checkAllOrNone(value) {
              // Not sent without a command.
              if (!hasCommand(getFormValues(this))) {
                return true;
              }

              const args = textToArguments(value ?? '');

              return (
                args.every((argument) => argument === '') ||
                args.every((argument) => argument !== '')
              );
            }
          )
      }),
      ...(isCommandRequired && {
        command: object({ id: number(), name: string() })
          .nullable()
          // Not yup's `{}` for an object left out, which would pass.
          .default(null)
          .test(
            'required-without-inherited-command',
            t(labelRequired),
            function checkCommandRequired() {
              return hasCommand(getFormValues(this));
            }
          )
      })
    })
  };
};

// The command and its arguments, which wait for it.
export const getCheckCommandBlock = ({
  endpoint,
  hasInheritedCommand = () => false,
  isCommandRequired = false,
  resource: { baseEndpoint, idPrefix },
  title,
  validateArguments = false
}: CheckCommandOptions & {
  // The command selector, offering the active check commands.
  endpoint: string;
  resource: ResourceOptions;
  // Untranslated, above the command.
  title?: string;
}): FieldBlock<CheckCommandDetail> => {
  const hasCommand = (values: FormValues): boolean =>
    !!(values.checkOptions as Partial<CheckCommandValues> | undefined)
      ?.command || hasInheritedCommand(values);

  return {
    defaultValues: { checkOptions: defaultCheckCommand },
    detailDecoders: {
      checkOptions: JsonDecoder.optional(
        JsonDecoder.nullable(checkCommandDecoder)
      ).map((value) => value ?? defaultCheckCommand)
    },
    detailKeyMap: { checkOptions: 'check_options' },
    getInputs: ({ isMassChange, t }) => ({
      checkCommand: getBlock({
        columns: [
          {
            ...(title && { additionalLabel: t(title) }),
            connectedAutocomplete: buildSelector({
              baseEndpoint,
              customQueryParameters: [
                { name: 'type[]', value: 'Check' },
                { name: 'is_activated', value: true }
              ],
              endpoint,
              getOptionLabel: (option) => (option as SelectEntry)?.name,
              queryKey: `${idPrefix}-check-command`
            }),
            dataTestId: `${idPrefix}-check-options-command`,
            fieldName: 'checkOptions.command',
            ...(isCommandRequired &&
              !isMassChange && {
                getRequired: (values: FormikValues) =>
                  !hasInheritedCommand(values)
              }),
            label: t(labelCheckCommand),
            type: InputType.SingleConnectedAutocomplete
          },
          {
            dataTestId: `${idPrefix}-check-options-args`,
            fieldName: 'checkOptions.args',
            // Arguments without a command are refused.
            getDisabled: (values) => !hasCommand(values),
            label: t(labelArguments),
            text: { placeholder: '!arg1!arg2' },
            type: InputType.Text
          }
        ],
        idPrefix,
        name: 'check-options'
      })
    }),
    getSchema: ({ isMassChange, t }) =>
      getCheckCommandSchema({
        hasCommand,
        // A mass change requires nothing.
        isCommandRequired: isCommandRequired && !isMassChange,
        t,
        validateArguments
      }),
    toPayload: (values) => {
      const { checkOptions = defaultCheckCommand } = values as {
        checkOptions?: CheckCommandValues;
      };

      return {
        check_options: {
          // Left over from a command since removed, they would be refused.
          args: hasCommand(values) ? textToArguments(checkOptions.args) : [],
          command_id: checkOptions.command?.id ?? null
        }
      };
    }
  };
};
