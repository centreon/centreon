import { InputType } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { array } from 'yup';

import {
  hostFormHostCategoriesEndpoint,
  hostFormHostGroupsEndpoint,
  hostFormHostsEndpoint,
  hostTemplateFormServiceTemplatesEndpoint
} from '../../api/endpoints';
import { namedEntityDecoder } from '../../api/namedEntityDecoders';
import type { NamedEntity } from '../../models';
import {
  labelChildHosts,
  labelHostCategories,
  labelHostGroups,
  labelLinkedServiceTemplates,
  labelParentAndChildHost,
  labelParentHosts,
  labelRelations,
  labelRequired
} from '../../translatedLabels';
import { buildSelector, toIds } from '../selector';
import { type UpdateModeField, withUpdateMode } from '../updateMode';
import type { FormSection, SectionContext } from './models';

interface RelationsDetail {
  categories: Array<NamedEntity>;
  childHosts: Array<NamedEntity>;
  groups: Array<NamedEntity>;
  parentHosts: Array<NamedEntity>;
  serviceTemplates: Array<NamedEntity>;
}

const getCategoriesInput = ({ t }: Pick<SectionContext, 't'>) => ({
  connectedAutocomplete: buildSelector({
    chipColor: 'primary',
    endpoint: hostFormHostCategoriesEndpoint,
    queryKey: 'host-form-categories'
  }),
  dataTestId: 'host-form-categories',
  fieldName: 'categories',
  label: t(labelHostCategories),
  type: InputType.MultiConnectedAutocomplete
});

// In legacy's order: what the template deploys, then its categories.
const getTemplateRelationInputs = ({ t }: SectionContext) => [
  {
    connectedAutocomplete: buildSelector({
      chipColor: 'primary',
      endpoint: hostTemplateFormServiceTemplatesEndpoint,
      queryKey: 'host-template-form-service-templates'
    }),
    dataTestId: 'host-form-service-templates',
    fieldName: 'serviceTemplates',
    label: t(labelLinkedServiceTemplates),
    type: InputType.MultiConnectedAutocomplete
  },
  getCategoriesInput({ t })
];

const getRelationInputs = ({
  t,
  isCloudPlatform,
  isMassChange
}: SectionContext) => [
  {
    connectedAutocomplete: buildSelector({
      chipColor: 'primary',
      endpoint: hostFormHostGroupsEndpoint,
      queryKey: 'host-form-groups'
    }),
    dataTestId: 'host-form-groups',
    fieldName: 'groups',
    // `CreateHostInput` counts at least one group on a cloud platform and
    // leaves it optional elsewhere, so the field follows the platform.
    getRequired: () => isCloudPlatform && !isMassChange,
    label: t(labelHostGroups),
    type: InputType.MultiConnectedAutocomplete
  },
  getCategoriesInput({ t }),
  {
    connectedAutocomplete: buildSelector({
      chipColor: 'primary',
      endpoint: hostFormHostsEndpoint,
      queryKey: 'host-form-parent-hosts'
    }),
    dataTestId: 'host-form-parent-hosts',
    fieldName: 'parentHosts',
    label: t(labelParentHosts),
    type: InputType.MultiConnectedAutocomplete
  },
  {
    connectedAutocomplete: buildSelector({
      chipColor: 'primary',
      endpoint: hostFormHostsEndpoint,
      queryKey: 'host-form-child-hosts'
    }),
    dataTestId: 'host-form-child-hosts',
    fieldName: 'childHosts',
    label: t(labelChildHosts),
    type: InputType.MultiConnectedAutocomplete
  }
];

const getHostRelationsSchema = ({
  t,
  isCloudPlatform,
  isMassChange
}: Pick<SectionContext, 'isCloudPlatform' | 'isMassChange' | 't'>) => ({
  // A host must belong to a group on cloud and need not anywhere else:
  // `CreateHostInput` counts them only under `WhenPlatform(forCloud: true)`.
  groups:
    isCloudPlatform && !isMassChange
      ? array().min(1, t(labelRequired))
      : array().notRequired(),
  // As the legacy form, a host cannot be both parent and child.
  parentHosts: array().test(
    'is-not-also-a-child',
    t(labelParentAndChildHost),
    (parents, { parent: values }) =>
      !(parents ?? []).some(({ id }) =>
        (values.childHosts ?? []).some(
          (child: { id: number }) => child.id === id
        )
      )
  )
});

export const relations: FormSection<RelationsDetail> = {
  defaultValues: {
    categories: [],
    childHosts: [],
    groups: [],
    parentHosts: [],
    serviceTemplates: []
  },
  detailDecoders: {
    categories: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Category'),
      'Categories'
    ),
    childHosts: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Child host'),
      'Child hosts'
    ),
    groups: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Group'),
      'Groups'
    ),
    parentHosts: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Parent host'),
      'Parent hosts'
    ),
    serviceTemplates: JsonDecoder.array(
      JsonDecoder.object(namedEntityDecoder, 'Service template'),
      'Service templates'
    )
  },
  detailKeyMap: {
    childHosts: 'child_hosts',
    parentHosts: 'parent_hosts',
    serviceTemplates: 'service_templates'
  },
  // Each list carries its update mode in a mass change, as in legacy.
  getInputs: (context) =>
    (context.isTemplate
      ? getTemplateRelationInputs(context)
      : getRelationInputs(context)
    ).map((input) =>
      withUpdateMode({
        field: input.fieldName as UpdateModeField,
        input,
        isMassChange: context.isMassChange,
        t: context.t
      })
    ),
  // A template has no group nor parent and child hosts to check.
  getSchema: ({ t, isCloudPlatform, isMassChange, isTemplate }) =>
    isTemplate
      ? {}
      : getHostRelationsSchema({ isCloudPlatform, isMassChange, t }),
  hostOnlyFields: ['childHosts', 'groups', 'parentHosts'],
  label: labelRelations,
  templateOnlyFields: ['serviceTemplates'],
  toPayload: (values, { isTemplate }) => {
    const { groups, categories, parentHosts, childHosts, serviceTemplates } =
      values as {
        categories: Array<{ id: number }> | null;
        childHosts: Array<{ id: number }> | null;
        groups: Array<{ id: number }> | null;
        parentHosts: Array<{ id: number }> | null;
        serviceTemplates?: Array<{ id: number }> | null;
      };

    if (isTemplate) {
      return {
        category_ids: toIds(categories),
        service_template_ids: toIds(serviceTemplates)
      };
    }

    return {
      category_ids: toIds(categories),
      child_host_ids: toIds(childHosts),
      host_group_ids: toIds(groups),
      parent_host_ids: toIds(parentHosts)
    };
  }
};
