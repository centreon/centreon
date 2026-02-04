import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { includes, keys, pickBy } from 'ramda';
import ConfigurationBase from '../ConfigurationBase';
import { ResourceType } from '../models';
import useColumns from './Columns/useColumns';
import { initialValues, useInputs, useValidationSchema } from './Form';
import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import { Filters } from './models';
import {
  labelAddCommand,
  labelCommands,
  labelConnectors,
  labelWelcomePageDescription,
  labelWelcomePageTitle
} from './translatedLabels';
import useCommands from './useCommands';
import { useUserPermissions } from './useUserPermissions';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

const Commands = (): ReactElement => {
  const { t } = useTranslation();

  const { columns } = useColumns();
  const { groups, inputs } = useInputs();
  const validationSchema = useValidationSchema();

  const { api, filtersConfiguration } = useCommands();

  const { canEdit, editorPermissions } = useUserPermissions();

  return (
    <ConfigurationBase<Filters>
      actions={{
        delete: ({ isFromMonitoringConnectors, type }) =>
          !isFromMonitoringConnectors &&
          includes(type, keys(pickBy(Boolean, editorPermissions))),
        duplicate: ({ type }) =>
          includes(type, keys(pickBy(Boolean, editorPermissions))),
        edit: canEdit,
        enableDisable: ({ type }) =>
          includes(type, keys(pickBy(Boolean, editorPermissions))),
        viewDetails: true
      }}
      api={api}
      columns={columns}
      columnsAtomKey={columnsAtomKey}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      filtersAtom={filtersAtom}
      filtersAtomKey={filtersAtomKey}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      form={{ defaultValues: initialValues, groups, inputs, validationSchema }}
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      labels={{
        title: t(labelCommands),
        welcomePage: {
          actions: {
            create: t(labelAddCommand)
          },
          description: t(labelWelcomePageDescription),
          title: t(labelWelcomePageTitle)
        }
      }}
      navbar={[
        {
          label: labelCommands,
          link: '/configuration/commands'
        },
        {
          label: labelConnectors,
          link: '/main.php?p=60806'
        }
      ]}
      resourceType={ResourceType.Command}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
    />
  );
};

export default Commands;
