import { useAtom } from 'jotai';
import ConfigurationBase from '../ConfigurationBase';
import { configurationAtom } from '../atoms';
import { Endpoints, ResourceType } from '../models';

import { useEffect } from 'react';
import useColumns from './Columns/useColumns';
import {
  bulkDeleteHostGroupEndpoint,
  bulkDisableHostGroupEndpoint,
  bulkDuplicateHostGroupEndpoint,
  bulkEnableHostGroupEndpoint,
  getHostGroupEndpoint,
  hostGroupsListEndpoint
} from './api/endpoints';

const HostGroups = () => {
  const { columns } = useColumns();

  const hostGroupsendpoints: Endpoints = {
    getAll: hostGroupsListEndpoint,
    getOne: getHostGroupEndpoint,
    deleleteOne: getHostGroupEndpoint,
    delete: bulkDeleteHostGroupEndpoint,
    duplicate: bulkDuplicateHostGroupEndpoint,
    disable: bulkDisableHostGroupEndpoint,
    enable: bulkEnableHostGroupEndpoint
  };

  const [configuration, setConfiguration] = useAtom(configurationAtom);

  useEffect(() => {
    setConfiguration({
      resourceType: ResourceType.HostGroup,
      endpoints: hostGroupsendpoints
    });
  }, []);

  if (!configuration?.endpoints || !configuration.resourceType) {
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
