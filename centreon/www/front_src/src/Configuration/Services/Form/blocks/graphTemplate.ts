import { InputType, type SelectEntry } from '@centreon/ui';

import { optionalNamedEntityDecoder } from '../../../ResourceForm/api/decoders';
import type {
  FieldBlock,
  NamedEntity,
  ResourceOptions
} from '../../../ResourceForm/models';
import { buildSelector } from '../../../ResourceForm/selector';
import { labelServiceGraphTemplate } from '../../translatedLabels';

// How the service's performance data is graphed. On-premise only: its selector
// route exists on-premise only, and the cloud API refuses the field.
export const getGraphTemplateBlock = ({
  endpoint,
  resource: { baseEndpoint, idPrefix }
}: {
  endpoint: string;
  resource: ResourceOptions;
}): FieldBlock<{ graphTemplate: NamedEntity | null }> => ({
  defaultValues: { graphTemplate: null },
  detailDecoders: {
    graphTemplate: optionalNamedEntityDecoder('Graph template')
  },
  detailKeyMap: { graphTemplate: 'graph_template' },
  getInputs: ({ t }) => ({
    graphTemplate: {
      connectedAutocomplete: buildSelector({
        baseEndpoint,
        endpoint,
        getOptionLabel: (option) => (option as SelectEntry)?.name,
        queryKey: `${idPrefix}-graph-template`
      }),
      dataTestId: `${idPrefix}-graph-template`,
      fieldName: 'graphTemplate',
      label: t(labelServiceGraphTemplate),
      type: InputType.SingleConnectedAutocomplete
    }
  }),
  getSchema: () => ({}),
  isAvailable: ({ isCloudPlatform }) => !isCloudPlatform,
  toPayload: (values) => ({
    graph_template_id:
      (values.graphTemplate as NamedEntity | null | undefined)?.id ?? null
  })
});
