import ConfigurationBase from '../ConfigurationBase';
import useColumns from './Columns/useColumns';

import { defaultValues, useFormInputs, useValidationSchema } from './Form';
import { defaultSelectedColumnIds, filtersInitialValues } from './utils';

import { ResourceType } from '../models';
import useAdditionnalConnectors from './useAdditionnalConnectors';

const AdditionnalConnectors = () => {
  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs();
  const { validationSchema } = useValidationSchema();

  const { api, filtersConfiguration } = useAdditionnalConnectors();

  return (
    <ConfigurationBase
      columns={columns}
      resourceType={ResourceType.AdditionalConfigurations}
      form={{ inputs, groups, validationSchema, defaultValues }}
      api={api}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      hasWriteAccess={true}
      actions={{
        delete: true
      }}
    />
  );
};

export default AdditionnalConnectors;
