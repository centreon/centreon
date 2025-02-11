import { useAtom } from 'jotai';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import ConfigurationBase from '../ConfigurationBase';
import { configurationAtom, filtersAtom } from '../atoms';
import {
  Endpoints,
  FieldType,
  FilterConfiguration,
  ResourceType
} from '../models';
import useColumns from './Columns/useColumns';
import {
  bulkDeleteHostGroupEndpoint,
  bulkDisableHostGroupEndpoint,
  bulkDuplicateHostGroupEndpoint,
  bulkEnableHostGroupEndpoint,
  getHostGroupEndpoint,
  hostGroupsListEndpoint
} from './api/endpoints';

import { isEmpty, not } from 'ramda';
import { labelAlias, labelName, labelStatus } from './translatedLabels';
import { defaultSelectedColumnIds, filtersInitialValues } from './utils';

const HostGroups = () => {
  const { t } = useTranslation();
  const { columns } = useColumns();

  const [configuration, setConfiguration] = useAtom(configurationAtom);
  const [filters, setFilters] = useAtom(filtersAtom);

  const hostGroupsendpoints: Endpoints = {
    getAll: hostGroupsListEndpoint,
    getOne: getHostGroupEndpoint,
    deleleteOne: getHostGroupEndpoint,
    delete: bulkDeleteHostGroupEndpoint,
    duplicate: bulkDuplicateHostGroupEndpoint,
    disable: bulkDisableHostGroupEndpoint,
    enable: bulkEnableHostGroupEndpoint
  };

  const filtersConfiguration: Array<FilterConfiguration> = [
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
  ];

  useEffect(() => {
    setConfiguration({
      resourceType: ResourceType.HostGroup,
      endpoints: hostGroupsendpoints,
      filtersConfiguration,
      filtersInitialValues,
      defaultSelectedColumnIds
    });

    setFilters(filtersInitialValues);
  }, []);

  if (
    not(
      configuration?.endpoints &&
        configuration?.resourceType &&
        configuration?.filtersConfiguration &&
        configuration?.filtersInitialValues &&
        !isEmpty(configuration?.defaultSelectedColumnIds) &&
        !isEmpty(filters)
    )
  ) {
    return;
  }

  return (
    <ConfigurationBase
      columns={columns}
      resourceType={ResourceType.HostGroup}
      Form={<div />}
    />
  );
};

export default HostGroups;
