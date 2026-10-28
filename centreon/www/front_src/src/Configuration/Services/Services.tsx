import { EditNoteOutlined as MassChangeIcon } from '@mui/icons-material';

import {
  isAdditiveInheritanceEnabledAtom,
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { useAtomValue, useSetAtom } from 'jotai';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import { formStateAtom } from '../ConfigurationBase/atoms';
import { labelMassChange } from '../ConfigurationBase/translatedLabels';
import { type Actions, ResourceType } from '../models';
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
import useHostNameFilterFromUrl from './useHostNameFilterFromUrl';
import useServices from './useServices';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  defaultSortField,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

const Services = () => {
  const { t } = useTranslation();

  const userPermissions = useAtomValue(userPermissionsAtom);
  const canEdit = !!userPermissions?.configuration_service_write;
  // Read once and handed to every hook: the fields, the rules that guard them
  // and the API slice each section owns have to agree on the platform.
  const isCloudPlatform = !!useAtomValue(platformFeaturesAtom)?.isCloudPlatform;
  const isAdditiveInheritanceEnabled = useAtomValue(
    isAdditiveInheritanceEnabledAtom
  );

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
  const { validationSchema } = useValidationSchema({
    isAdditiveInheritanceEnabled,
    isCloudPlatform
  });
  const defaultValues = useMemo(
    () => getDefaultValues({ isAdditiveInheritanceEnabled, isCloudPlatform }),
    [isAdditiveInheritanceEnabled, isCloudPlatform]
  );
  // The same sections, without what names one service, nothing required, and
  // an update mode on every list (MON-213088).
  const { groups: massChangeGroups, inputs: massChangeInputs } = useFormInputs({
    canEdit,
    isAdditiveInheritanceEnabled,
    isCloudPlatform,
    isMassChange: true
  });
  const { validationSchema: massChangeValidationSchema } = useValidationSchema({
    isAdditiveInheritanceEnabled,
    isCloudPlatform,
    isMassChange: true
  });
  const massChange = useMemo(() => {
    const context = {
      isAdditiveInheritanceEnabled,
      isCloudPlatform,
      isMassChange: true
    };

    return {
      adapter: getMassChangeAdapter(context),
      defaultValues: getMassChangeDefaultValues(context),
      getConfirmationWarning: getMassChangeWarning(t),
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
  const setFormState = useSetAtom(formStateAtom);
  useHostNameFilterFromUrl();

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
          onClick: (rows) =>
            setFormState({
              id: null,
              isOpen: true,
              mode: 'massChange',
              resource: null,
              selection: rows.map(({ id, name }) => ({
                id: id as number,
                name: name as string
              }))
            })
        }
      ],
      // Keeps the status toggle on screen, disabled, for a read-only user.
      rowActionsWithoutWriteAccess: true,
      // A read-only user still opens a service, on a frozen form.
      viewDetails: true
    }),
    [canEdit, setFormState]
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
