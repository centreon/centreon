import { EditNoteOutlined as MassChangeIcon } from '@mui/icons-material';

import {
  isAdditiveInheritanceEnabledAtom,
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { type QueryClient, useQueryClient } from '@tanstack/react-query';
import { useAtom, useAtomValue } from 'jotai';
import { isEmpty, prop, uniqBy } from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import { formStateAtom } from '../ConfigurationBase/atoms';
import { labelMassChange } from '../ConfigurationBase/translatedLabels';
import { type Actions, type ResourceRow, ResourceType } from '../models';
import { joinWarnings } from '../ResourceForm/massChange';
import type { FormValues } from '../ResourceForm/models';
import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  massChangeRowsAtom,
  selectedColumnIdsAtom
} from './atoms';
import useColumns from './Columns/useColumns';
import {
  getDefaultValues,
  getMassChangeAdapter,
  getMassChangeDefaultValues,
  getMassChangeWarning,
  useFormInputs,
  useValidationSchema
} from './Form';
import type { Filters } from './models';
import {
  labelCreateService,
  labelServices,
  labelServicesDescription,
  labelSharedServicesKeepFirstHost,
  labelWelcomeToServices
} from './translatedLabels';
import useServices from './useServices';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  defaultSortField,
  filtersAtomKey,
  filtersInitialValues,
  getServiceRowId,
  getSharedServiceIds
} from './utils';

// The service rows the listing has loaded, whatever the page.
const getShownRows = (queryClient: QueryClient): Array<ResourceRow> =>
  queryClient
    .getQueriesData<{ result?: Array<ResourceRow> }>({
      queryKey: ['listResources']
    })
    .filter(([queryKey]) => queryKey.includes(ResourceType.Service))
    .flatMap(([, data]) => data?.result ?? []);

const Services = () => {
  const { t } = useTranslation();

  const userPermissions = useAtomValue(userPermissionsAtom);
  // Read once and handed to every hook: the fields and the rules that guard
  // them have to agree on the platform.
  const isCloudPlatform = !!useAtomValue(platformFeaturesAtom)?.isCloudPlatform;
  const isAdditiveInheritanceEnabled = useAtomValue(
    isAdditiveInheritanceEnabledAtom
  );
  const canEdit = !!userPermissions?.configuration_service_write;

  const { columns } = useColumns();
  const { api, filtersConfiguration } = useServices({
    isAdditiveInheritanceEnabled,
    isCloudPlatform
  });
  const { groups, inputs } = useFormInputs({
    canEdit,
    isAdditiveInheritanceEnabled,
    isCloudPlatform
  });
  const { validationSchema } = useValidationSchema({ isCloudPlatform });
  const defaultValues = useMemo(
    () => getDefaultValues({ isCloudPlatform }),
    [isCloudPlatform]
  );
  const { groups: massChangeGroups, inputs: massChangeInputs } = useFormInputs({
    canEdit,
    isAdditiveInheritanceEnabled,
    isCloudPlatform,
    isMassChange: true
  });
  const { validationSchema: massChangeValidationSchema } = useValidationSchema({
    isCloudPlatform,
    isMassChange: true
  });
  const [formState, setFormState] = useAtom(formStateAtom);
  const [massChangeRows, setMassChangeRows] = useAtom(massChangeRowsAtom);
  const queryClient = useQueryClient();
  const { selection } = formState;

  const massChange = useMemo(() => {
    const context = {
      isAdditiveInheritanceEnabled,
      isCloudPlatform,
      isMassChange: true
    };

    // The API saves a service linked to several hosts on its first host only.
    // Read when the confirmation opens, on the rows loaded by then and the
    // services still selected after a partial failure.
    const hasSharedService = (): boolean => {
      const selectedIds = (selection ?? []).map(({ id }) => id);

      return !isEmpty(
        getSharedServiceIds(
          massChangeRows.filter(({ id }) => selectedIds.includes(id as number)),
          getShownRows(queryClient)
        )
      );
    };

    return {
      adapter: getMassChangeAdapter(context),
      defaultValues: getMassChangeDefaultValues(context),
      getConfirmationWarning: (values: FormValues): string | null =>
        joinWarnings([
          hasSharedService() ? t(labelSharedServicesKeepFirstHost) : null,
          getMassChangeWarning({ t, values })
        ]),
      groups: massChangeGroups,
      inputs: massChangeInputs,
      validationSchema: massChangeValidationSchema
    };
  }, [
    massChangeRows,
    queryClient,
    selection,
    isAdditiveInheritanceEnabled,
    isCloudPlatform,
    massChangeGroups,
    massChangeInputs,
    massChangeValidationSchema,
    t
  ]);

  const actions: Actions = useMemo(
    () => ({
      delete: () => canEdit,
      duplicate: () => canEdit,
      edit: canEdit,
      enableDisable: () => canEdit,
      massive: canEdit,
      massiveActions: [
        {
          dataTestId: 'mass-change',
          Icon: MassChangeIcon,
          label: labelMassChange,
          onClick: (rows) => {
            setMassChangeRows(rows);
            setFormState({
              id: null,
              isOpen: true,
              mode: 'massChange',
              resource: null,
              // A service on several hosts is one service: one write for it.
              selection: uniqBy(prop('id'), rows).map(({ id, name }) => ({
                id: id as number,
                name: name as string
              }))
            });
          }
        }
      ],
      // Keeps the status toggle on screen, disabled, for a read-only user.
      rowActionsWithoutWriteAccess: true,
      // A row opens its service, read-only without write access.
      viewDetails: true
    }),
    [canEdit, setFormState, setMassChangeRows]
  );

  return (
    <ConfigurationBase<Filters>
      actions={actions}
      api={api}
      columns={columns}
      columnsAtomKey={columnsAtomKey}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      defaultSortField={defaultSortField}
      filtersAtom={filtersAtom}
      filtersAtomKey={filtersAtomKey}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      // The default width fits neither five filters nor the names the
      // autocomplete holds.
      filtersPanelWidth={60}
      form={{ defaultValues, groups, inputs, massChange, validationSchema }}
      formVariant="panel"
      getRowId={getServiceRowId}
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      labels={{
        description: t(labelServicesDescription),
        title: t(labelServices),
        welcomePage: {
          actions: {
            create: t(labelCreateService)
          },
          title: t(labelWelcomeToServices)
        }
      }}
      resourceType={ResourceType.Service}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
    />
  );
};

export default Services;
