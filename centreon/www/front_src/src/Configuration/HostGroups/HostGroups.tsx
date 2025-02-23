import ConfigurationBase from '../ConfigurationBase';
import useColumns from './Columns/useColumns';
import useHostGroups from './useHostGroups';

import { defaultValues, useFormInputs, useValidationSchema } from './Form';

import { ResourceType } from '../models';

const HostGroups = () => {
  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs();
  const { validationSchema } = useValidationSchema();

  useHostGroups();

  return (
    <ConfigurationBase
      columns={columns}
      resourceType={ResourceType.HostGroup}
      form={{ inputs, groups, validationSchema, defaultValues }}
    />
  );
};

export default HostGroups;
