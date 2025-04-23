import { useMemo } from 'react';

import { platformFeaturesAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';
import { pluck } from 'ramda';
import { useTranslation } from 'react-i18next';

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

import { APIType, FieldType, FilterConfiguration } from '../models';

import { labelAlias, labelName, labelStatus } from './translatedLabels';

interface UseHostGroupsState {
  api: APIType;
  filtersConfiguration: Array<FilterConfiguration>;
}

const adaptFormToApiPayload =
  ({ isCloudPlatform }) =>
  ({ name, alias, comment, geoCoords, hosts, resourceAccessRules, icon }) => {
    const cloudProperties = isCloudPlatform
      ? { resource_access_rules: pluck('id', resourceAccessRules) }
      : {};

    const payload = {
      name,
      alias,
      comment,
      geo_coords: geoCoords,
      hosts: pluck('id', hosts),
      icon_id: icon?.id || null,
      ...cloudProperties
    };

    return payload;
  };

const useHostGroups = (): UseHostGroupsState => {
  const { t } = useTranslation();
  const platformFeatures = useAtomValue(platformFeaturesAtom);
  const isCloudPlatform = platformFeatures?.isCloudPlatform;

  const api: APIType = useMemo(
    () => ({
      endpoints: {
        getAll: hostGroupsListEndpoint,
        getOne: getHostGroupEndpoint,
        deleteOne: getHostGroupEndpoint,
        delete: bulkDeleteHostGroupEndpoint,
        duplicate: bulkDuplicateHostGroupEndpoint,
        disable: bulkDisableHostGroupEndpoint,
        enable: bulkEnableHostGroupEndpoint,
        create: hostGroupsListEndpoint,
        update: getHostGroupEndpoint
      },
      decoders: { getAll: hostGroupsListDecoder, getOne: hostGroupDecoder },
      adapter: adaptFormToApiPayload({ isCloudPlatform })
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

  return {
    api,
    filtersConfiguration
  };
};

export default useHostGroups;
