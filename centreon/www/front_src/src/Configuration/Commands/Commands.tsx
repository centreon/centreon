import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import useColumns from './Columns/useColumns';
import useCommands from './useCommands';

import { ResourceType } from '../models';
import { defaultValues, useInputs, useValidationSchema } from './Form';
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

const Commands = () => {
  const { t } = useTranslation();

  const { columns } = useColumns();
  const { groups, inputs } = useInputs();
  const validationSchema = useValidationSchema();

  const { api, filtersConfiguration } = useCommands();

  return (
    <ConfigurationBase<Filters>
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      columnsAtomKey={columnsAtomKey}
      filtersAtomKey={filtersAtomKey}
      filtersAtom={filtersAtom}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
      columns={columns}
      resourceType={ResourceType.Command}
      form={{ inputs, groups, validationSchema, defaultValues }}
      api={api}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      actions={{
        delete: true,
        edit: true,
        enableDisable: true
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
