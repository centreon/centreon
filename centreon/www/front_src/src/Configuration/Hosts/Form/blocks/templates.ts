import { InputType } from '@centreon/ui';

import { path as getPath } from 'ramda';
import { JsonDecoder } from 'ts.data.json';

import { namedEntityDecoder } from '../../../ResourceForm/api/decoders';
import { getBlock } from '../../../ResourceForm/grid';
import type {
  FieldBlock,
  NamedEntity,
  ResourceOptions
} from '../../../ResourceForm/models';
import { toIds } from '../../../ResourceForm/selector';
import { withUpdateMode } from '../../../ResourceForm/updateMode';
import {
  labelCreateServicesLinkedToTemplates,
  labelTemplateReplacementDeletesServices,
  labelTemplates
} from '../../translatedLabels';
import Templates, { type TemplateRow } from '../Templates';

interface TemplatesDetail {
  createServicesLinkedToTemplates: boolean;
  templates: Array<TemplateRow>;
}

// The host's templates, in the order they are inherited from, and whether
// saving creates their services.
export const templates = ({
  resource: { idPrefix }
}: {
  resource: ResourceOptions;
}): FieldBlock<TemplatesDetail> => ({
  defaultValues: {
    // As legacy creates a host: its templates' services along with it.
    createServicesLinkedToTemplates: true,
    templates: []
  },
  detailDecoders: {
    // Never read back. Off, as legacy opens an existing host: saving it does
    // not create its templates' services again unless asked to.
    createServicesLinkedToTemplates: JsonDecoder.constant(false),
    // In the order they are inherited from.
    templates: JsonDecoder.optional(
      JsonDecoder.array<TemplateRow>(
        JsonDecoder.object(namedEntityDecoder, 'Template'),
        'Templates'
      )
    ).map((value) => value ?? [])
  },
  detailKeyMap: {
    createServicesLinkedToTemplates: 'create_services_linked_to_templates'
  },
  getInputs: ({ isCloudPlatform, isMassChange, t }) => ({
    templates: getBlock({
      columns: [
        withUpdateMode({
          field: 'templates',
          idPrefix,
          input: {
            custom: { Component: Templates },
            dataTestId: `${idPrefix}-templates`,
            fieldName: 'templates',
            label: t(labelTemplates),
            type: InputType.Custom
          },
          isMassChange,
          t
        }),
        // Cloud always creates them.
        ...(isCloudPlatform
          ? []
          : [
              {
                dataTestId: `${idPrefix}-create-services-linked-to-templates`,
                fieldName: 'createServicesLinkedToTemplates',
                label: t(labelCreateServicesLinkedToTemplates),
                type: InputType.Switch
              }
            ])
      ],
      idPrefix,
      name: 'templates-block'
    })
  }),
  getSchema: () => ({}),
  massChange: {
    defaultValues: { createServicesLinkedToTemplates: false },
    // Replacing a host's templates deletes the services they created, on
    // every selected host: the confirmation says so.
    getWarning: (t) => (values) =>
      getPath(['massChangeModes', 'templates'], values) === 'replacement'
        ? t(labelTemplateReplacementDeletesServices)
        : null,
    updateModeFields: { templates: [['template_ids']] }
  },
  toPayload: (values, { isCloudPlatform }) => {
    const { createServicesLinkedToTemplates = true, templates: rows = [] } =
      values as {
        createServicesLinkedToTemplates?: boolean;
        templates?: Array<TemplateRow>;
      };

    return {
      // Refused on cloud, which always creates them.
      ...(!isCloudPlatform && {
        create_services_linked_to_templates: createServicesLinkedToTemplates
      }),
      // In order, rows left unpicked aside.
      template_ids: toIds(
        rows.filter((template): template is NamedEntity => !!template)
      )
    };
  }
});
