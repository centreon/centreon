import {
  buildListingDecoder,
  type InputPropsWithoutGroup,
  InputType,
  type SelectEntry
} from '@centreon/ui';

import type { TFunction } from 'i18next';
import { JsonDecoder } from 'ts.data.json';
import { array } from 'yup';

import { namedEntityDecoder as namedEntityFields } from '../../../Hosts/api/namedEntityDecoders';
import { buildSelector as buildHostsSelector } from '../../../Hosts/Form/selector';
import { servicesBaseEndpoint } from '../../api/endpoints';
import type { NamedEntity } from '../../models';
import {
  labelRelations,
  labelServiceCategories,
  labelServiceGraphTemplate,
  labelServiceGroups,
  labelServiceSeverity,
  labelServiceSeverityWithLevel,
  labelServiceTraps
} from '../../translatedLabels';
import { withUpdateMode } from '../updateMode';
import type { FormSection, SectionContext } from './models';

// Placeholders until the backend exposes them, on the model of the hosts'
// `/configuration/hosts/host_groups`. The service groups selector answers
// with the groups the user can see only.
export const serviceFormServiceGroupsEndpoint =
  '/configuration/services/service_groups';
export const serviceFormTrapsEndpoint = '/configuration/services/traps';
// Moved from the legacy Extended Info tab (MON-213087): placeholders too.
export const serviceFormGraphTemplatesEndpoint =
  '/configuration/services/graph_templates';
export const serviceFormServiceCategoriesEndpoint =
  '/configuration/services/service_categories';
export const serviceFormServiceSeveritiesEndpoint =
  '/configuration/services/service_severities';

interface Trap {
  id: number;
  name: string;
  vendor?: string | null;
}

type Severity = NamedEntity & { level?: number | null };

interface RelationsDetail {
  categories: Array<NamedEntity>;
  graphTemplate: NamedEntity | null;
  serviceGroups: Array<NamedEntity>;
  severity: Severity | null;
  traps?: Array<NamedEntity>;
}

const namedEntityDecoder = JsonDecoder.object<NamedEntity>(
  { id: JsonDecoder.number, name: JsonDecoder.string },
  'Entity'
);

// A trap is shown as `vendor - trap`, as in the legacy form, which the
// frontend composes from the trap and its vendor.
const trapDecoder = JsonDecoder.object<Trap>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    vendor: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.string))
  },
  'Trap'
).map<NamedEntity>(({ id, name, vendor }) => ({
  id,
  name: vendor ? `${vendor} - ${name}` : name
}));

// The selectors answer in Hydra, which the autocomplete cannot read unmapped.
const buildListDecoder = (entityDecoder: JsonDecoder.Decoder<NamedEntity>) =>
  buildListingDecoder({
    apiFormat: 'JSON-LD',
    entityDecoder,
    entityDecoderName: 'Entity',
    listingDecoderName: 'Entity List'
  });

const buildSelector = ({
  endpoint,
  entityDecoder,
  queryKey
}: {
  endpoint: string;
  entityDecoder: JsonDecoder.Decoder<NamedEntity>;
  queryKey: string;
}) => ({
  additionalConditionParameters: [],
  baseEndpoint: servicesBaseEndpoint,
  chipColor: 'primary' as const,
  customQueryParameters: [],
  decoder: buildListDecoder(entityDecoder),
  endpoint,
  queryKey,
  useNewAPIFormat: true
});

// API Platform takes ids where the form holds the options the autocompletes
// selected.
const toIds = (entities: Array<{ id: number }> | null | undefined) =>
  (entities ?? []).map(({ id }) => id);

const severityDecoder = JsonDecoder.object<Severity>(
  {
    ...namedEntityFields,
    level: JsonDecoder.optional(JsonDecoder.nullable(JsonDecoder.number))
  },
  'Severity'
);

const severitiesListDecoder = buildListingDecoder({
  apiFormat: 'JSON-LD',
  entityDecoder: severityDecoder,
  entityDecoderName: 'Severity',
  listingDecoderName: 'Severity List'
});

// A severity reads `name (level)`, as in the legacy select.
const getSeverityLabel =
  (t: TFunction) =>
  (option: unknown): string => {
    const severity = option as Severity | null;

    if (!severity) {
      return '';
    }

    return severity.level === undefined || severity.level === null
      ? severity.name
      : t(labelServiceSeverityWithLevel, {
          level: severity.level,
          name: severity.name
        });
  };

// Service groups and traps: the legacy Relations tab.
const getGroupsAndTrapsInputs = ({
  t,
  isCloudPlatform,
  isMassChange
}: SectionContext): Array<InputPropsWithoutGroup> => [
  withUpdateMode({
    field: 'serviceGroups',
    input: {
      connectedAutocomplete: buildSelector({
        endpoint: serviceFormServiceGroupsEndpoint,
        entityDecoder: namedEntityDecoder,
        queryKey: 'service-form-service-groups'
      }),
      dataTestId: 'service-form-service-groups',
      fieldName: 'serviceGroups',
      // Unlike the host groups of a host, optional on cloud too.
      label: t(labelServiceGroups),
      type: InputType.MultiConnectedAutocomplete
    },
    isMassChange,
    t
  }),
  // Traps are an on-premise feature: the cloud API refuses them.
  ...(isCloudPlatform
    ? []
    : [
        withUpdateMode({
          field: 'traps',
          input: {
            connectedAutocomplete: buildSelector({
              endpoint: serviceFormTrapsEndpoint,
              entityDecoder: trapDecoder,
              queryKey: 'service-form-traps'
            }),
            dataTestId: 'service-form-traps',
            fieldName: 'traps',
            label: t(labelServiceTraps),
            type: InputType.MultiConnectedAutocomplete
          },
          isMassChange,
          t
        })
      ])
];

// Categories, severity and graph template: from the legacy Extended Info tab
// (MON-213087).
const getClassificationInputs = ({
  t,
  isCloudPlatform,
  isMassChange
}: SectionContext): Array<InputPropsWithoutGroup> => [
  withUpdateMode({
    field: 'categories',
    input: {
      connectedAutocomplete: buildHostsSelector({
        chipColor: 'primary',
        endpoint: serviceFormServiceCategoriesEndpoint,
        queryKey: 'service-form-categories'
      }),
      dataTestId: 'service-form-categories',
      fieldName: 'categories',
      label: t(labelServiceCategories),
      type: InputType.MultiConnectedAutocomplete
    },
    isMassChange,
    t
  }),
  {
    connectedAutocomplete: buildHostsSelector({
      decoder: severitiesListDecoder,
      endpoint: serviceFormServiceSeveritiesEndpoint,
      // The options and the picked value alike.
      getOptionLabel: getSeverityLabel(t),
      getRenderedOptionText: getSeverityLabel(t),
      queryKey: 'service-form-severity'
    }),
    dataTestId: 'service-form-severity',
    fieldName: 'severity',
    label: t(labelServiceSeverity),
    type: InputType.SingleConnectedAutocomplete
  },
  // On-premise only.
  ...(isCloudPlatform
    ? []
    : [
        {
          connectedAutocomplete: buildHostsSelector({
            endpoint: serviceFormGraphTemplatesEndpoint,
            getOptionLabel: (option) => (option as SelectEntry)?.name,
            queryKey: 'service-form-graph-template'
          }),
          dataTestId: 'service-form-graph-template',
          fieldName: 'graphTemplate',
          label: t(labelServiceGraphTemplate),
          type: InputType.SingleConnectedAutocomplete
        }
      ])
];

// Groups and traps | categories, severity and graph template, as the Figma
// panel lays them out.
const getRelationInputs = (
  context: SectionContext
): Array<InputPropsWithoutGroup> => [
  {
    fieldName: 'relations-layout',
    grid: {
      className: 'grid-cols-1 gap-x-8 @[600px]:grid-cols-2',
      columns: [
        {
          fieldName: 'relations-groups',
          grid: {
            className: 'grid-cols-1',
            columns: getGroupsAndTrapsInputs(context)
          },
          label: 'service-form-relations-groups',
          type: InputType.Grid
        },
        {
          fieldName: 'relations-classification',
          grid: {
            className: 'grid-cols-1',
            columns: getClassificationInputs(context)
          },
          label: 'service-form-relations-classification',
          type: InputType.Grid
        }
      ]
    },
    label: 'service-form-relations-layout',
    type: InputType.Grid
  }
];

// The Figma panel's third section: the legacy Relations tab (service groups
// and traps) with the categories, severity and graph template of the legacy
// Extended Info tab. There is no ACL resource groups field, unlike the hosts:
// a service is visible through its host, groups and categories.
export const relations: FormSection<RelationsDetail> = {
  defaultValues: {
    categories: [],
    graphTemplate: null,
    serviceGroups: [],
    severity: null,
    traps: []
  },
  detailDecoders: {
    // Only the categories the user may see: the server keeps the others.
    categories: JsonDecoder.optional(
      JsonDecoder.array(
        JsonDecoder.object(namedEntityFields, 'Category'),
        'Categories'
      )
    ).map((value) => value ?? []),
    // On-premise only: never in a cloud response.
    graphTemplate: JsonDecoder.optional(
      JsonDecoder.nullable(
        JsonDecoder.object(namedEntityFields, 'Graph template')
      )
    ).map((value) => value ?? null),
    serviceGroups: JsonDecoder.array(namedEntityDecoder, 'Service groups'),
    // Left out too when the user's ACLs do not cover it; the server then
    // keeps it on update, as for hosts.
    severity: JsonDecoder.optional(JsonDecoder.nullable(severityDecoder)).map(
      (value) => value ?? null
    ),
    // Absent on cloud, where a service has no trap.
    traps: JsonDecoder.optional(JsonDecoder.array(trapDecoder, 'Traps'))
  },
  detailKeyMap: {
    graphTemplate: 'graph_template',
    serviceGroups: 'service_groups'
  },
  getInputs: getRelationInputs,
  getSchema: ({ isCloudPlatform }) => ({
    categories: array().notRequired(),
    serviceGroups: array().notRequired(),
    ...(isCloudPlatform ? {} : { traps: array().notRequired() })
  }),
  label: labelRelations,
  toPayload: (values, { isCloudPlatform }) => {
    const { categories, graphTemplate, serviceGroups, severity, traps } =
      values as {
        categories?: Array<{ id: number }> | null;
        graphTemplate?: NamedEntity | null;
        serviceGroups: Array<{ id: number }> | null;
        severity?: Severity | null;
        traps: Array<{ id: number }> | null;
      };

    return {
      // The visible ones only: the server adds back those the user cannot see.
      category_ids: toIds(categories),
      service_group_ids: toIds(serviceGroups),
      severity_id: severity?.id ?? null,
      ...(!isCloudPlatform && {
        graph_template_id: graphTemplate?.id ?? null,
        trap_ids: toIds(traps)
      })
    };
  }
};
