import {
  buildListingDecoder,
  type InputPropsWithoutGroup,
  InputType
} from '@centreon/ui';

import { JsonDecoder } from 'ts.data.json';
import { array } from 'yup';

import { servicesBaseEndpoint } from '../../api/endpoints';
import type { NamedEntity } from '../../models';
import {
  labelRelations,
  labelServiceGroups,
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

interface Trap {
  id: number;
  name: string;
  vendor?: string | null;
}

interface RelationsDetail {
  serviceGroups: Array<NamedEntity>;
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

const getRelationInputs = ({
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

// The legacy "Relations" tab: service groups and traps. There is no ACL
// resource groups field, unlike the hosts: a service is visible through its
// host, groups and categories.
export const relations: FormSection<RelationsDetail> = {
  defaultValues: {
    serviceGroups: [],
    traps: []
  },
  detailDecoders: {
    serviceGroups: JsonDecoder.array(namedEntityDecoder, 'Service groups'),
    // Absent on cloud, where a service has no trap.
    traps: JsonDecoder.optional(JsonDecoder.array(trapDecoder, 'Traps'))
  },
  detailKeyMap: {
    serviceGroups: 'service_groups'
  },
  getInputs: getRelationInputs,
  getSchema: ({ isCloudPlatform }) => ({
    serviceGroups: array().notRequired(),
    ...(isCloudPlatform ? {} : { traps: array().notRequired() })
  }),
  label: labelRelations,
  toPayload: (values, { isCloudPlatform }) => {
    const { serviceGroups, traps } = values as {
      serviceGroups: Array<{ id: number }> | null;
      traps: Array<{ id: number }> | null;
    };

    return {
      service_group_ids: toIds(serviceGroups),
      ...(isCloudPlatform ? {} : { trap_ids: toIds(traps) })
    };
  }
};
