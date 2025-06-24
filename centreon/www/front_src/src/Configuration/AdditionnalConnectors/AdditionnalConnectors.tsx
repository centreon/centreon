import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import useColumns from './Columns/useColumns';
import useAdditionnalConnectors from './useAdditionnalConnectors';

import { ResourceType } from '../models';
import { defaultValues, useFormInputs, useValidationSchema } from './Form';
import { defaultSelectedColumnIds, filtersInitialValues } from './utils';

import {
  labelAddAdditionalConfigurations,
  labelAdditionalConnectorConfiguration,
  labelPageDescription,
  labelWelcomeToAdditionalConfigurations
} from './translatedLabels';

const AdditionnalConnectors = () => {
  const { t } = useTranslation();

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
      actions={{
        delete: true,
        edit: true
      }}
      labels={{
        title: t(labelAdditionalConnectorConfiguration),
        welcomePage: {
          title: t(labelWelcomeToAdditionalConfigurations),
          description: t(labelPageDescription),
          actions: {
            create: t(labelAddAdditionalConfigurations)
          }
        }
      }}
    />
  );
};

export default AdditionnalConnectors;
