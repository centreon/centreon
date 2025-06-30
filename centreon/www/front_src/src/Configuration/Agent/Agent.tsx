import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import { useColumns } from './Columns/useColumns';
import useAgent from './useAgent';

import { ResourceType } from '../Common/models';
import { defaultValues, useFormInputs, useValidationSchema } from './Form';
import { defaultSelectedColumnIds, filtersInitialValues } from './utils';

import {
  labelAddAgentConfiguration,
  labelAgentsConfigurations,
  labelCollapse,
  labelExpand,
  labelWelcomeDescription,
  labelWelcomeToTheAgentsConfigurationPage
} from './translatedLabels';

const Agent = () => {
  const { t } = useTranslation();

  const columns = useColumns();
  const { groups, inputs } = useFormInputs();
  const validationSchema = useValidationSchema();

  const { api, filtersConfiguration } = useAgent();

  return (
    <ConfigurationBase
      columns={columns}
      resourceType={ResourceType.PollerAgentConfiguration}
      form={{
        inputs,
        groups,
        validationSchema,
        defaultValues
      }}
      api={api}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      actions={{
        delete: true,
        edit: true
      }}
      labels={{
        title: t(labelAgentsConfigurations),
        welcomePage: {
          title: t(labelWelcomeToTheAgentsConfigurationPage),
          description: t(labelWelcomeDescription),
          actions: {
            create: t(labelAddAgentConfiguration)
          }
        }
      }}
      listAdditionalProps={{
        subItems: {
          canCheckSubItems: false,
          enable: true,
          getRowProperty: () => 'pollers',
          labelExpand: t(labelExpand),
          labelCollapse: t(labelCollapse)
        }
      }}
    />
  );
};

export default Agent;
