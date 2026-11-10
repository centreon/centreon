import { InputType } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';

import { namedEntityDecoder } from '../../../ResourceForm/api/decoders';
import { getBlock } from '../../../ResourceForm/grid';
import type {
  FieldBlock,
  NamedEntity,
  ResourceOptions
} from '../../../ResourceForm/models';
import type { TemplateRow } from '../../../ResourceForm/OrderedTemplates';
import { toIds } from '../../../ResourceForm/selector';
import { labelTemplates } from '../../translatedLabels';
import ParentTemplates from '../ParentTemplates';

interface TemplatesDetail {
  templates: Array<TemplateRow>;
}

// The template's parents, in the order they are inherited from.
export const getTemplatesBlock = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<TemplatesDetail> => ({
  defaultValues: { templates: [] },
  detailDecoders: {
    templates: JsonDecoder.optional(
      JsonDecoder.array<TemplateRow>(
        JsonDecoder.object(namedEntityDecoder, 'Template'),
        'Templates'
      )
    ).map((value) => value ?? [])
  },
  getInputs: ({ t }) => ({
    templates: getBlock({
      columns: [
        {
          custom: { Component: ParentTemplates },
          dataTestId: `${idPrefix}-templates`,
          fieldName: 'templates',
          label: t(labelTemplates),
          type: InputType.Custom
        }
      ],
      idPrefix,
      name: 'templates-block'
    })
  }),
  getSchema: () => ({}),
  toPayload: (values) => ({
    // In order, rows left unpicked aside.
    template_ids: toIds(
      ((values.templates ?? []) as Array<TemplateRow>).filter(
        (template): template is NamedEntity => !!template
      )
    )
  })
});
