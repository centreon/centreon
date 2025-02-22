import { equals } from 'ramda';
import {
  labelAlias,
  labelApplyResourceAccessRule,
  labelComment,
  labelExtendedInformation,
  labelGeneralInformation,
  labelGeographicCoordinates,
  labelGroupMembers,
  labelName,
  labelResourceAccessRule,
  labelSelectHosts
} from '../translatedLabels';

export const getListingResponse = (resourceType) => ({
  result: Array.from({ length: 8 }, (_, i) => ({
    id: i,
    name: equals(i, 5) ? 'hostGroup0'.repeat(20) : `${resourceType} ${i}`,
    alias: equals(i, 5)
      ? 'alias'.repeat(20)
      : `alias for  ${resourceType} ${i}`,
    enabled_hosts_count: i % 2 ? 0 : 3 * i,
    disabled_hosts_count: i % 2 ? 5 * i : 0,
    is_activated: !!(i % 2)
  })),
  meta: {
    limit: 10,
    page: 1,
    total: 8
  }
});

export const hostsListEmptyResponse = {
  result: [],
  meta: {
    limit: 10,
    page: 1,
    total: 0
  }
};

export const getDetailsResponse = ({ isCloudPlatform = false }) => ({
  id: 1,
  name: 'host group 1 name',
  alias: 'host group 1 alias',
  hosts: [
    { id: 1, name: 'host 1' },
    { id: 2, name: 'host 2' },
    { id: 3, name: 'host 3' }
  ],
  ...(isCloudPlatform
    ? [
        {
          resource_access_rules: [
            { id: 1, name: 'rule 1' },
            { id: 2, name: 'rule 2' }
          ]
        }
      ]
    : []),
  geo_coords: '-40.16,98.22',
  comment: 'host group 1 comment',
  is_activated: true
});

export const getPayload = ({ isCloudPlatform = false }) => ({
  name: 'host group 1 name',
  alias: 'host group 1 alias',
  hosts: [1, 2, 3],
  ...(isCloudPlatform ? [{ resource_access_rules: ['rule 1', 'rule 2'] }] : []),
  geo_coords: '-40.16,98.22',
  comment: 'host group 1 comment'
});

export const getGroups = ({ isCloudPlatform = false }) => [
  {
    name: labelGeneralInformation
  },
  {
    name: labelGroupMembers
  },
  ...(isCloudPlatform
    ? [
        {
          name: labelResourceAccessRule
        }
      ]
    : []),
  { name: labelExtendedInformation }
];

// to remove if not used
export const getInputs = ({ isCloudPlatform = false }) => [
  {
    fieldName: 'name',
    label: labelName
  },
  {
    fieldName: 'alias',
    label: labelAlias
  },
  {
    fieldName: 'hosts',
    label: labelSelectHosts
  },
  ...(isCloudPlatform
    ? [
        {
          fieldName: 'resourceAccessRules',
          label: labelApplyResourceAccessRule
        }
      ]
    : []),
  {
    fieldName: 'geoCoords',
    label: labelGeographicCoordinates
  },
  {
    fieldName: 'comment',
    label: labelComment
  }
];
