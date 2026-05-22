import { equals } from 'ramda';

import {
  labelAdditionalInformation,
  labelGeneralInformation,
  labelGroupMembers,
  labelResourceAccessRule
} from '../translatedLabels';
import centreonWallpaper from './assets/centreon-wallpaper.jpg';
import cypressLogo from './assets/cypress-logo.jpg';

export const getListingResponse = (resourceType) => ({
  meta: {
    limit: 10,
    page: 1,
    total: 8
  },
  result: Array.from({ length: 8 }, (_, i) => ({
    alias: equals(i, 5)
      ? 'alias'.repeat(20)
      : `alias for  ${resourceType} ${i}`,
    disabled_hosts_count: i % 2 ? 5 * i : 0,
    enabled_hosts_count: i % 2 ? 0 : 3 * i,
    icon: equals(i, 0)
      ? {
          id: 1,
          name: 'cypress_logo',
          url: cypressLogo
        }
      : null,
    id: i,
    is_activated: !!(i % 2),
    name: equals(i, 5) ? 'hostGroup0'.repeat(20) : `${resourceType} ${i}`
  }))
});

export const hostsListEmptyResponse = {
  meta: {
    limit: 10,
    page: 1,
    total: 0
  },
  result: []
};

export const getDetailsResponse = ({ isCloudPlatform = false }) => ({
  alias: 'host group 1 alias',
  hosts: [
    { id: 1, name: 'host 1' },
    { id: 2, name: 'host 2' },
    { id: 3, name: 'host 3' }
  ],
  id: 1,
  name: 'host group 1 name',
  ...(isCloudPlatform
    ? {
        resource_access_rules: [
          { id: 1, name: 'rule 1' },
          { id: 2, name: 'rule 2' }
        ]
      }
    : {}),
  comment: 'host group 1 comment',
  geo_coords: '-40.16,98.22',
  icon: {
    id: 1,
    name: 'cypress_logo',
    url: cypressLogo
  },
  is_activated: true
});

export const getPayload = ({ isCloudPlatform = false }) => ({
  alias: 'host group 1 alias',
  hosts: [1, 2, 3],
  name: 'host group 1 name',
  ...(isCloudPlatform ? { resource_access_rules: [1, 2] } : {}),
  comment: 'host group 1 comment',
  geo_coords: '-40.16,98.22',
  icon_id: 1
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
  { name: labelAdditionalInformation }
];

export const listImagesResponse = {
  meta: {
    limit: 10,
    page: 1,
    search: {},
    sort_by: {},
    total: 1
  },
  result: [
    {
      directory: 'ppm',
      id: 1,
      name: 'cypress_logo',
      url: cypressLogo
    },
    {
      directory: 'ppm',
      id: 2,
      name: 'centreon_wallpaper',
      url: centreonWallpaper
    }
  ]
};
