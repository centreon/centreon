import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import useColumns from './Columns/useColumns';
import useCommands from './useCommands';

import { ResourceType } from '../models';
import {
  initialValues,
  useCanManageCommand,
  useInputs,
  useValidationSchema
} from './Form';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import { Filters } from './models';

import {
  labelAddCommand,
  labelCommands,
  labelWelcomePageDescription,
  labelWelcomePageTitle
} from './translatedLabels';

const Commands = (): ReactElement => {
  const { t } = useTranslation();

  const { columns } = useColumns();
  const { groups, inputs } = useInputs();
  const validationSchema = useValidationSchema();

  const { api, filtersConfiguration } = useCommands();

  const { canEdit } = useCanManageCommand();

  return (
    <ConfigurationBase<Filters>
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      columnsAtomKey={columnsAtomKey}
      filtersAtomKey={filtersAtomKey}
      filtersAtom={filtersAtom}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
      columns={columns}
      resourceType={ResourceType.Command}
      form={{ inputs, groups, validationSchema, defaultValues: initialValues }}
      api={api}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      actions={{
        delete: true,
        enableDisable: true,
        duplicate: true,
        edit: canEdit,
        viewDetails: true
      }}
      labels={{
        title: t(labelCommands),
        welcomePage: {
          title: t(labelWelcomePageTitle),
          description: t(labelWelcomePageDescription),
          actions: {
            create: t(labelAddCommand)
          }
        }
      }}
    />
  );
};

export default Commands;
