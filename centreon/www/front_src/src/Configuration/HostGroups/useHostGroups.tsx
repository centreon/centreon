import { useEffect, useMemo } from 'react';

import { platformFeaturesAtom } from '@centreon/ui-context';
import { useAtomValue, useSetAtom } from 'jotai';
import { pluck } from 'ramda';
import { useTranslation } from 'react-i18next';
import { configurationAtom, filtersAtom } from '../atoms';

import { defaultSelectedColumnIds, filtersInitialValues } from './utils';

import {
  bulkDeleteHostGroupEndpoint,
  bulkDisableHostGroupEndpoint,
  bulkDuplicateHostGroupEndpoint,
  bulkEnableHostGroupEndpoint,
  getHostGroupEndpoint,
  hostGroupDecoder,
  hostGroupsListDecoder,
  hostGroupsListEndpoint
} from './api';

import {
  Endpoints,
  FieldType,
  FilterConfiguration,
  ResourceType
} from '../models';

import { labelAlias, labelName, labelStatus } from './translatedLabels';

const adaptFormToApiPayload =
  ({ isCloudPlatform }) =>
  ({ name, alias, comment, geoCoords, hosts, resourceAccessRules }) => {
    const cloudOnlyProperty = isCloudPlatform
      ? { resource_access_rules: pluck('id', resourceAccessRules) }
      : {};

    const payload = {
      name,
      alias,
      comment,
      geo_coords: geoCoords,
      hosts: pluck('id', hosts),
      ...cloudOnlyProperty
    };

    return payload;
  };

const useHostGroups = () => {
  const { t } = useTranslation();

  const setConfiguration = useSetAtom(configurationAtom);
  const setFilters = useSetAtom(filtersAtom);
  const platformFeatures = useAtomValue(platformFeaturesAtom);
  const isCloudPlatform = platformFeatures?.isCloudPlatform;

  const hostGroupsEndpoints: Endpoints = useMemo(
    () => ({
      getAll: hostGroupsListEndpoint,
      getOne: getHostGroupEndpoint,
      deleteOne: getHostGroupEndpoint,
      delete: bulkDeleteHostGroupEndpoint,
      duplicate: bulkDuplicateHostGroupEndpoint,
      disable: bulkDisableHostGroupEndpoint,
      enable: bulkEnableHostGroupEndpoint,
      create: hostGroupsListEndpoint,
      update: getHostGroupEndpoint
    }),
    []
  );

  const filtersConfiguration: Array<FilterConfiguration> = useMemo(
    () => [
      {
        name: t(labelName),
        fieldName: 'name',
        fieldType: FieldType.Text
      },
      {
        name: t(labelAlias),
        fieldName: 'alias',
        fieldType: FieldType.Text
      },
      {
        name: t(labelStatus),
        fieldType: FieldType.Status
      }
    ],
    []
  );

  useEffect(() => {
    setConfiguration({
      resourceType: ResourceType.HostGroup,
      api: {
        endpoints: hostGroupsEndpoints,
        decoders: { getAll: hostGroupsListDecoder, getOne: hostGroupDecoder },
        adapter: adaptFormToApiPayload({ isCloudPlatform })
      },
      filtersConfiguration,
      filtersInitialValues,
      defaultSelectedColumnIds
    });

    setFilters(filtersInitialValues);
  }, [setConfiguration, setFilters, hostGroupsEndpoints, filtersConfiguration]);
};

export default useHostGroups;
