import { InputType, type SelectEntry } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { optionalNamedEntityDecoder } from '../api/decoders';
import { argumentsToText, textToArguments } from '../commandArguments';
import { getBlock } from '../grid';
import type {
  FieldBlock,
  FormValues,
  NamedEntity,
  ResourceOptions
} from '../models';
import { buildSelector } from '../selector';
import { labelArguments, labelCheckCommand } from '../translatedLabels';

interface CheckCommandValues {
  args: string;
  command: NamedEntity | null;
}

// Within the API's `check_options`, which other blocks fill too.
interface CheckCommandDetail {
  checkOptions: CheckCommandValues;
}

const defaultCheckCommand: CheckCommandValues = { args: '', command: null };

const checkCommandDecoder = JsonDecoder.object<CheckCommandValues>(
  {
    args: JsonDecoder.optional(
      JsonDecoder.array(JsonDecoder.string, 'Check command arguments')
    ).map((value) => argumentsToText(value ?? [])),
    command: optionalNamedEntityDecoder('Check command')
  },
  'Check options'
);

// The command and its arguments, which wait for it.
export const getCheckCommandBlock = ({
  endpoint,
  hasInheritedCommand = () => false,
  resource: { baseEndpoint, idPrefix },
  title
}: {
  // The command selector, offering the active check commands.
  endpoint: string;
  // A command the resource runs without one of its own: arguments go with it.
  hasInheritedCommand?: (values: FormValues) => boolean;
  resource: ResourceOptions;
  // Untranslated, above the command.
  title: string;
}): FieldBlock<CheckCommandDetail> => ({
  defaultValues: { checkOptions: defaultCheckCommand },
  detailDecoders: {
    checkOptions: JsonDecoder.optional(
      JsonDecoder.nullable(checkCommandDecoder)
    ).map((value) => value ?? defaultCheckCommand)
  },
  detailKeyMap: { checkOptions: 'check_options' },
  getInputs: ({ t }) => ({
    checkCommand: getBlock({
      columns: [
        {
          additionalLabel: t(title),
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
          label: t(labelCheckCommand),
          type: InputType.SingleConnectedAutocomplete
        },
        {
          dataTestId: `${idPrefix}-check-options-args`,
          fieldName: 'checkOptions.args',
          // Arguments without a command, own or inherited, are refused.
          getDisabled: (values) =>
            !values.checkOptions?.command && !hasInheritedCommand(values),
          label: t(labelArguments),
          text: { placeholder: '!arg1!arg2' },
          type: InputType.Text
        }
      ],
      idPrefix,
      name: 'check-options'
    })
  }),
  getSchema: () => ({}),
  toPayload: (values) => {
    const { checkOptions = defaultCheckCommand } = values as {
      checkOptions?: CheckCommandValues;
    };

    return {
      check_options: {
        // Left over from a command since removed, they would be refused.
        args:
          checkOptions.command || hasInheritedCommand(values)
            ? textToArguments(checkOptions.args)
            : [],
        command_id: checkOptions.command?.id ?? null
      }
    };
  }
});
