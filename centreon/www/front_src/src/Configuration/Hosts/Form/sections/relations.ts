import { InputType } from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { array } from 'yup';

import {
  hostFormHostCategoriesEndpoint,
  hostFormHostGroupsEndpoint,
  hostFormHostsEndpoint
} from '../../api/endpoints';
import { namedEntityDecoder } from '../../api/namedEntityDecoders';
import type { NamedEntity } from '../../models';
import {
  labelChildHosts,
  labelHostCategories,
  labelHostGroups,
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
}

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
  {
    connectedAutocomplete: buildSelector({
      chipColor: 'primary',
      endpoint: hostFormHostCategoriesEndpoint,
      queryKey: 'host-form-categories'
    }),
    dataTestId: 'host-form-categories',
    fieldName: 'categories',
    label: t(labelHostCategories),
    type: InputType.MultiConnectedAutocomplete
  },
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

export const relations: FormSection<RelationsDetail> = {
  defaultValues: {
    categories: [],
    childHosts: [],
    groups: [],
    parentHosts: []
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
    )
  },
  detailKeyMap: {
    childHosts: 'child_hosts',
    parentHosts: 'parent_hosts'
  },
  // Each list carries its update mode in a mass change, as in legacy.
  getInputs: (context) =>
    getRelationInputs(context).map((input) =>
      withUpdateMode({
        field: input.fieldName as UpdateModeField,
        input,
        isMassChange: context.isMassChange,
        t: context.t
      })
    ),
  getSchema: ({ t, isCloudPlatform, isMassChange }) => ({
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
  }),
  label: labelRelations,
  toPayload: (values) => {
    const { groups, categories, parentHosts, childHosts } = values as {
      categories: Array<{ id: number }> | null;
      childHosts: Array<{ id: number }> | null;
      groups: Array<{ id: number }> | null;
      parentHosts: Array<{ id: number }> | null;
    };

    return {
      category_ids: toIds(categories),
      child_host_ids: toIds(childHosts),
      host_group_ids: toIds(groups),
      parent_host_ids: toIds(parentHosts)
    };
  }
};
