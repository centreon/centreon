import { InputType, type SelectEntry } from '@centreon/ui';

import { number, object, string } from 'yup';

import { optionalNamedEntityDecoder } from '../../../ResourceForm/api/decoders';
import { getBlock } from '../../../ResourceForm/grid';
import type {
  FieldBlock,
  NamedEntity,
  ResourceOptions
} from '../../../ResourceForm/models';
import { buildSelector } from '../../../ResourceForm/selector';
import { labelRequired } from '../../../ResourceForm/translatedLabels';
import { labelTemplate } from '../../translatedLabels';

interface TemplateDetail {
  template: NamedEntity | null;
}

const templateSchema = object({ id: number(), name: string() }).nullable();

// The one template a service inherits from directly (a host has a list). The
// API requires one on a cloud platform. A mass change does not move it, so the
// block is left out of one.
export const getTemplateBlock = ({
  resource: { baseEndpoint, idPrefix },
  serviceTemplatesEndpoint
}: {
  resource: ResourceOptions;
  serviceTemplatesEndpoint: string;
}): FieldBlock<TemplateDetail> => ({
  defaultValues: { template: null },
  detailDecoders: { template: optionalNamedEntityDecoder('Template') },
  getInputs: ({ isCloudPlatform, t }) => ({
    templates: getBlock({
      columns: [
        {
          connectedAutocomplete: buildSelector({
            baseEndpoint,
            endpoint: serviceTemplatesEndpoint,
            getOptionLabel: (option) => (option as SelectEntry)?.name,
            queryKey: `${idPrefix}-template`
          }),
          dataTestId: `${idPrefix}-template`,
          fieldName: 'template',
          label: t(labelTemplate),
          required: isCloudPlatform,
          type: InputType.SingleConnectedAutocomplete
        }
      ],
      idPrefix,
      name: 'template'
    })
  }),
  getSchema: ({ isCloudPlatform, t }) => ({
    template: isCloudPlatform
      ? templateSchema.required(t(labelRequired))
      : templateSchema
  }),
  isAvailable: ({ isMassChange }) => !isMassChange,
  toPayload: (values) => {
    const { template } = values as { template?: NamedEntity | null };

    return { service_template_id: template?.id ?? null };
  }
});
