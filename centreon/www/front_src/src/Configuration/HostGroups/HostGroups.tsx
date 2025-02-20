import { useEffect, useMemo } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { isEmpty, not, pluck } from 'ramda';
import { useTranslation } from 'react-i18next';
import { configurationAtom, filtersAtom } from '../atoms';

import ConfigurationBase from '../ConfigurationBase';

import {
  Endpoints,
  FieldType,
  FilterConfiguration,
  ResourceType
} from '../models';
import useColumns from './Columns/useColumns';

import { hostGroupDecoder, hostGroupsListDecoder } from './api/decoders';
import {
  bulkDeleteHostGroupEndpoint,
  bulkDisableHostGroupEndpoint,
  bulkDuplicateHostGroupEndpoint,
  bulkEnableHostGroupEndpoint,
  getHostGroupEndpoint,
  hostGroupsListEndpoint
} from './api/endpoints';

import { platformFeaturesAtom } from '@centreon/ui-context';
import { defaultValues } from './Form/defaultValues';
import useFormInputs from './Form/useFormInputs';
import useValidationSchema from './Form/useValidationSchema';
import { labelAlias, labelName, labelStatus } from './translatedLabels';
import { defaultSelectedColumnIds, filtersInitialValues } from './utils';

const HostGroups = () => {
  const { t } = useTranslation();
  const { columns } = useColumns();

  const [configuration, setConfiguration] = useAtom(configurationAtom);
  const [filters, setFilters] = useAtom(filtersAtom);
  const platformFeatures = useAtomValue(platformFeaturesAtom);
  const isCloudPlatform = platformFeatures?.isCloudPlatform;

  const { groups, inputs } = useFormInputs();
  const { validationSchema } = useValidationSchema();

  const adaptFormToApiPayload = ({
    name,
    alias,
    comment,
    geoCoords,
    hosts,
    resourceAccessRules
  }) => {
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
        adapter: adaptFormToApiPayload
      },
      filtersConfiguration,
      filtersInitialValues,
      defaultSelectedColumnIds
    });

    setFilters(filtersInitialValues);
  }, [setConfiguration, setFilters, hostGroupsEndpoints, filtersConfiguration]);

  const isConfigurationValid = useMemo(
    () =>
      configuration?.api?.endpoints &&
      configuration?.resourceType &&
      configuration?.filtersConfiguration &&
      !isEmpty(configuration?.defaultSelectedColumnIds) &&
      !isEmpty(configuration?.filtersInitialValues) &&
      !isEmpty(filters),
    [configuration, filters]
  );

  if (not(isConfigurationValid)) {
    return;
  }

  return (
    <ConfigurationBase
      columns={columns}
      resourceType={ResourceType.HostGroup}
      form={{ inputs, groups, validationSchema, defaultValues }}
    />
  );
};

export default HostGroups;
