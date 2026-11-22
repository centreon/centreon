import { useFetchQuery } from '@centreon/ui';
import { userPermissionsAtom } from '@centreon/ui-context';

import { useAtomValue, useSetAtom } from 'jotai';
import { RESET } from 'jotai/utils';
import { ReactElement, useEffect, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { baseEndpoint } from '../../api/endpoint';
import ConfigurationBase from '../ConfigurationBase';
import { formStateAtom } from '../ConfigurationBase/atoms';
import { type Actions, ResourceType } from '../models';
import { aiProvidersEndpoint, aiProvidersListDecoder } from './api';
import {
  discoveredModelsAtom,
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import useColumns from './Columns/useColumns';
import { getDefaultValues, useFormInputs, useValidationSchema } from './Form';
import { Filters } from './models';
import {
  labelAddAiProvider,
  labelAiProviders,
  labelAiProvidersDescription,
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

  const userPermissions = useAtomValue(userPermissionsAtom);
  const canEdit = !!userPermissions?.administration_ai_provider_write;

  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs({ canEdit });
  const { validationSchema } = useValidationSchema();

  const { api, filtersConfiguration } = useAiProviders();

  // The API always activates the very first provider (there must be an enabled
  // one for AI features to work), so the Enabled switch starts on when no
  // provider exists yet and off otherwise, where enabling would disable the
  // currently active one. Only the add form reads these defaults. Keyed under
  // the listing, whose invalidation on create and delete refreshes it.
  const { data: providersCount } = useFetchQuery({
    decoder: aiProvidersListDecoder,
    getEndpoint: () => `${baseEndpoint}${aiProvidersEndpoint}?page=1&limit=1`,
    getQueryKey: () => ['listResources', 'aiProvidersCount']
  });

  // the models discovered for a provider are not offered for another one
  const { isOpen, id, mode } = useAtomValue(formStateAtom);
  const resetDiscoveredModels = useSetAtom(discoveredModelsAtom);

  useEffect(() => {
    resetDiscoveredModels(RESET);
  }, [isOpen, id, mode]);

  const defaultValues = useMemo(
    () => getDefaultValues({ isActivated: providersCount?.meta?.total === 0 }),
    [providersCount?.meta?.total]
  );

  // at most one provider is enabled: enabling one disables the other. The API
  // has no duplication and no batch operation for providers.
  const actions: Actions = useMemo(
    () => ({
      delete: () => canEdit,
      edit: canEdit,
      enableDisable: () => canEdit,
      viewDetails: true
    }),
    [canEdit]
  );

  return (
    <ConfigurationBase<Filters>
      actions={actions}
      api={api}
      columns={columns}
      columnsAtomKey={columnsAtomKey}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      filtersAtom={filtersAtom}
      filtersAtomKey={filtersAtomKey}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      filtersPanelWidth={60}
      form={{ defaultValues, groups, inputs, validationSchema }}
      formVariant="panel"
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      labels={{
        description: t(labelAiProvidersDescription),
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
