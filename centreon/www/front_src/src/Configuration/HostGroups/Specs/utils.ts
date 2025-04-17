import { equals } from 'ramda';
import {
  labelExtendedInformation,
  labelGeneralInformation,
  labelGroupMembers,
  labelResourceAccessRule
} from '../translatedLabels';

import centreonWallpaper from './assets/centreon-wallpaper.jpg';
import cypressLogo from './assets/cypress-logo.jpg';

export const getListingResponse = (resourceType) => ({
  result: Array.from({ length: 8 }, (_, i) => ({
    id: i,
    name: equals(i, 5) ? 'hostGroup0'.repeat(20) : `${resourceType} ${i}`,
    alias: equals(i, 5)
      ? 'alias'.repeat(20)
      : `alias for  ${resourceType} ${i}`,
    enabled_hosts_count: i % 2 ? 0 : 3 * i,
    disabled_hosts_count: i % 2 ? 5 * i : 0,
    is_activated: !!(i % 2),
    icon: equals(i, 0)
      ? {
          id: 1,
          name: 'cypress_logo',
          url: cypressLogo
        }
      : null
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
    ? {
        resource_access_rules: [
          { id: 1, name: 'rule 1' },
          { id: 2, name: 'rule 2' }
        ]
      }
    : {}),
  geo_coords: '-40.16,98.22',
  comment: 'host group 1 comment',
  is_activated: true,
  icon: {
    id: 1,
    name: 'cypress_logo',
    url: cypressLogo
  }
});

export const getPayload = ({ isCloudPlatform = false }) => ({
  name: 'host group 1 name',
  alias: 'host group 1 alias',
  hosts: [1, 2, 3],
  ...(isCloudPlatform ? { resource_access_rules: [1, 2] } : {}),
  geo_coords: '-40.16,98.22',
  comment: 'host group 1 comment',
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
  { name: labelExtendedInformation }
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
