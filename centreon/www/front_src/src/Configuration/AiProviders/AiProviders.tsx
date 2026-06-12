import { ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import { ResourceType } from '../models';
import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import useColumns from './Columns/useColumns';
import { initialValues, useInputs, useValidationSchema } from './Form';
import { Filters } from './models';
import {
  labelAddAiProvider,
  labelAiProviders,
  labelWelcomePageDescription,
  labelWelcomePageTitle
} from './translatedLabels';
import useAiProviders from './useAiProviders';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

const AiProviders = (): ReactElement => {
  const { t } = useTranslation();

  const { columns } = useColumns();
  const { groups, inputs } = useInputs();
  const validationSchema = useValidationSchema();

  const { api, filtersConfiguration } = useAiProviders();

  return (
    <ConfigurationBase<Filters>
      actions={{
        delete: () => true,
        edit: true,
        // at most one provider is enabled: enabling one disables the other
        enableDisable: () => true
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
        title: t(labelAiProviders),
        welcomePage: {
          actions: {
            create: t(labelAddAiProvider)
          },
          description: t(labelWelcomePageDescription),
          title: t(labelWelcomePageTitle)
        }
      }}
      resourceType={ResourceType.AiProvider}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
    />
  );
};

export default AiProviders;
