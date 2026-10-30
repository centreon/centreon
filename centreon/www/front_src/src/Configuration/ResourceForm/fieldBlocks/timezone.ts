import { InputType, type SelectEntry } from '@centreon/ui';

import { optionalNamedEntityDecoder } from '../api/decoders';
import type { FieldBlock, NamedEntity, ResourceOptions } from '../models';
import { buildSelector } from '../selector';
import { labelTimezone } from '../translatedLabels';

// The timezone the engine reads the time periods of a host in.
export const timezone = ({
  endpoint,
  resource: { baseEndpoint, idPrefix }
}: {
  endpoint: string;
  resource: ResourceOptions;
}): FieldBlock<{ timezone: NamedEntity | null }> => ({
  defaultValues: { timezone: null },
  detailDecoders: { timezone: optionalNamedEntityDecoder('Timezone') },
  getInputs: ({ t }) => ({
    timezone: {
      connectedAutocomplete: buildSelector({
        baseEndpoint,
        endpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        queryKey: `${idPrefix}-timezone`
      }),
      dataTestId: `${idPrefix}-timezone`,
      fieldName: 'timezone',
      label: t(labelTimezone),
      type: InputType.SingleConnectedAutocomplete
    }
  }),
  getSchema: () => ({}),
  toPayload: (values) => ({
    timezone_id: (values.timezone as NamedEntity | null | undefined)?.id ?? null
  })
});
