import ConfigurationBase from '../ConfigurationBase';
import useColumns from './Columns/useColumns';

import { defaultValues, useFormInputs, useValidationSchema } from './Form';

import { defaultSelectedColumnIds, filtersInitialValues } from './utils';

import { ResourceType } from '../models';
import useHostGroups from './useHostGroups';

const HostGroups = () => {
  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs();
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
    />
  );
};

export default HostGroups;
