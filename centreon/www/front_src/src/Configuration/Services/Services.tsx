import {
  isAdditiveInheritanceEnabledAtom,
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import useMassChangeAction from '../ConfigurationBase/useMassChangeAction';
import { type Actions, ResourceType } from '../models';
import type { FormValues } from '../ResourceForm/models';
import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
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
  labelWelcomeToServices
} from './translatedLabels';
import { useHostNameFilterFromUrl } from './useHostNameFilterFromUrl';
import useServices from './useServices';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  defaultSortField,
  filtersAtomKey,
  filtersInitialValues,
  getServiceRowId
} from './utils';

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
  const massChangeAction = useMassChangeAction();

  useHostNameFilterFromUrl();

  const massChange = useMemo(() => {
    const context = {
      isAdditiveInheritanceEnabled,
      isCloudPlatform,
      isMassChange: true
    };

    return {
      adapter: getMassChangeAdapter(context),
      defaultValues: getMassChangeDefaultValues(context),
      getConfirmationWarning: (values: FormValues): string | null =>
        getMassChangeWarning({ t, values }),
      groups: massChangeGroups,
      inputs: massChangeInputs,
      validationSchema: massChangeValidationSchema
    };
  }, [
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
      massiveActions: [massChangeAction],
      // Keeps the status toggle on screen, disabled, for a read-only user.
      rowActionsWithoutWriteAccess: true,
      // A row opens its service, read-only without write access.
      viewDetails: true
    }),
    [canEdit, massChangeAction]
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
