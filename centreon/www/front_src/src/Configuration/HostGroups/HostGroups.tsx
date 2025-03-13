import { useEffect, useMemo } from 'react';

import { useAtom } from 'jotai';
import { isEmpty, isNil, not } from 'ramda';
import { useTranslation } from 'react-i18next';
import {
  configurationAtom,
  filtersAtom,
  selectedColumnIdsAtom
} from '../atoms';

import ConfigurationBase from '../ConfigurationBase';
import Form from './Form/Form';

import {
  Endpoints,
  FieldType,
  FilterConfiguration,
  ResourceType
} from '../models';
import useColumns from './Columns/useColumns';

import { hostGroupsDecoderListDecoder } from './api/decoders';
import {
  bulkDeleteHostGroupEndpoint,
  bulkDisableHostGroupEndpoint,
  bulkDuplicateHostGroupEndpoint,
  bulkEnableHostGroupEndpoint,
  getHostGroupEndpoint,
  hostGroupsListEndpoint
} from './api/endpoints';

import { atomKey } from '../utils';
import { labelAlias, labelName, labelStatus } from './translatedLabels';
import { defaultSelectedColumnIds, filtersInitialValues } from './utils';

const HostGroups = () => {
  const { t } = useTranslation();
  const { columns } = useColumns();

  const [configuration, setConfiguration] = useAtom(configurationAtom);
  const [filters, setFilters] = useAtom(filtersAtom);
  const [selectedColumnIds, setSelectedColumnIds] = useAtom(
    selectedColumnIdsAtom
  );

  const hostGroupsEndpoints: Endpoints = useMemo(
    () => ({
      getAll: hostGroupsListEndpoint,
      getOne: getHostGroupEndpoint,
      deleteOne: getHostGroupEndpoint,
      delete: bulkDeleteHostGroupEndpoint,
      duplicate: bulkDuplicateHostGroupEndpoint,
      disable: bulkDisableHostGroupEndpoint,
      enable: bulkEnableHostGroupEndpoint
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
        decoders: { getAll: hostGroupsDecoderListDecoder }
      },
      filtersConfiguration,
      filtersInitialValues,
      defaultSelectedColumnIds
    });

    setFilters(filtersInitialValues);
    if (isNil(localStorage.getItem(`selectedColumn_${atomKey}`))) {
      setSelectedColumnIds(defaultSelectedColumnIds);
    }
  }, [setConfiguration, setFilters, hostGroupsEndpoints, filtersConfiguration]);

  const isConfigurationValid = useMemo(
    () =>
      configuration?.api?.endpoints &&
      configuration?.resourceType &&
      configuration?.filtersConfiguration &&
      !isEmpty(configuration?.defaultSelectedColumnIds) &&
      !isEmpty(configuration?.filtersInitialValues) &&
      !isEmpty(filters) &&
      !isEmpty(selectedColumnIds),
    [configuration, filters]
  );

  if (not(isConfigurationValid)) {
    return;
  }

  return (
    <ConfigurationBase
      columns={columns}
      resourceType={ResourceType.HostGroup}
      Form={<Form />}
    />
  );
};

export default HostGroups;
