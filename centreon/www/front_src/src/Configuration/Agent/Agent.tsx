import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import { useColumns } from './Columns/useColumns';
import useAgent from './useAgent';

import { ResourceType } from '../Common/models';
import {
  ActionButtons,
  defaultValues,
  useFormInputs,
  useValidationSchema
} from './Form';
import { defaultSelectedColumnIds, filtersInitialValues } from './utils';

import {
  labelAddAgentConfiguration,
  labelAgent,
  labelAgentsConfigurations,
  labelCollapse,
  labelDeleteAgent,
  labelDeleteAgentConfirmation,
  labelDeletePoller,
  labelDeletePollerConfirmation,
  labelExpand,
  labelPoller,
  labelWelcomeDescription,
  labelWelcomeToTheAgentsConfigurationPage
} from './translatedLabels';

const Agent = () => {
  const { t } = useTranslation();

  const columns = useColumns();
  const { groups, inputs } = useFormInputs();
  const validationSchema = useValidationSchema();

  const { api, filtersConfiguration, canDelete } = useAgent();

  return (
    <ConfigurationBase
      columns={columns}
      resourceType={ResourceType.PollerAgentConfiguration}
      form={{
        inputs,
        groups,
        validationSchema,
        defaultValues,
        ActionButtons
      }}
      api={api}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      actions={{
        delete: canDelete,
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
        },
        dialogs: {
          delete: {
            name: labelAgent.toLowerCase(),
            subItemName: labelPoller.toLowerCase(),
            title: t(labelDeleteAgent),
            description: t(labelDeleteAgentConfirmation),
            subItemTitle: t(labelDeletePoller),
            subItemDescription: t(labelDeletePollerConfirmation)
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
