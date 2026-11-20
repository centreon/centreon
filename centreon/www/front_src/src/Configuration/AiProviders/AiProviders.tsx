import { useFetchQuery } from '@centreon/ui';

import { ReactElement, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { baseEndpoint } from '../../api/endpoint';
import ConfigurationBase from '../ConfigurationBase';
import { ResourceType } from '../models';
import { aiProvidersEndpoint, aiProvidersListDecoder } from './api';
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

  // The API always activates the very first provider (there must be an enabled
  // one for AI features to work), so the Enabled switch starts on when no
  // provider exists yet and off otherwise, where enabling would disable the
  // currently active one. Only the add form reads these defaults.
  const { data: providersCount } = useFetchQuery({
    decoder: aiProvidersListDecoder,
    getEndpoint: () => `${baseEndpoint}${aiProvidersEndpoint}?page=1&limit=1`,
    getQueryKey: () => ['aiProvidersCount']
  });

  const defaultValues = useMemo(
    () => ({
      ...initialValues,
      isActivated: providersCount?.meta?.total === 0
    }),
    [providersCount?.meta?.total]
  );

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
      form={{ defaultValues, groups, inputs, validationSchema }}
      formVariant="panel"
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
