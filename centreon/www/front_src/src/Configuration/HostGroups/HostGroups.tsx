import { userPermissionsAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';

import ConfigurationBase from '../ConfigurationBase';
import useColumns from './Columns/useColumns';

import { defaultValues, useFormInputs, useValidationSchema } from './Form';
import { defaultSelectedColumnIds, filtersInitialValues } from './utils';

import { ResourceType } from '../models';
import useHostGroups from './useHostGroups';

const HostGroups = () => {
  const userPermissions = useAtomValue(userPermissionsAtom);
  const hasWriteAccess = !!userPermissions?.configuration_host_group_write;

  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs({ hasWriteAccess });
  const { validationSchema } = useValidationSchema();

  const { api, filtersConfiguration } = useHostGroups();

  return (
    <ConfigurationBase
      columns={columns}
      resourceType={ResourceType.HostGroup}
      form={{ inputs, groups, validationSchema, defaultValues }}
      api={api}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      hasWriteAccess={hasWriteAccess}
    />
  );
};

export default HostGroups;
