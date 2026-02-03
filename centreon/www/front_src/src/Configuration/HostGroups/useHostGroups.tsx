import { platformFeaturesAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { pluck } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { APIType, FieldType, FilterConfiguration } from '../models';
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
      alias,
      comment,
      geo_coords: geoCoords,
      hosts: pluck('id', hosts),
      icon_id: icon?.id || null,
      name,
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
      adapter: adaptFormToApiPayload({ isCloudPlatform }),
      decoders: { getAll: hostGroupsListDecoder, getOne: hostGroupDecoder },
      endpoints: {
        create: hostGroupsListEndpoint,
        delete: bulkDeleteHostGroupEndpoint,
        deleteOne: getHostGroupEndpoint,
        disable: bulkDisableHostGroupEndpoint,
        duplicate: bulkDuplicateHostGroupEndpoint,
        enable: bulkEnableHostGroupEndpoint,
        getAll: hostGroupsListEndpoint,
        getOne: getHostGroupEndpoint,
        update: getHostGroupEndpoint
      }
    }),
    []
  );

  const filtersConfiguration: Array<FilterConfiguration> = useMemo(
    () => [
      {
        fieldName: 'name',
        fieldType: FieldType.Text,
        name: t(labelName)
      },
      {
        fieldName: 'alias',
        fieldType: FieldType.Text,
        name: t(labelAlias)
      },
      {
        fieldType: FieldType.Status,
        name: t(labelStatus)
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
