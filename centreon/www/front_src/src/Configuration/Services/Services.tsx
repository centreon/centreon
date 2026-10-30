import { userPermissionsAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { object } from 'yup';

import ConfigurationBase from '../ConfigurationBase';
import { type Actions, type Form, ResourceType } from '../models';
import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import useColumns from './Columns/useColumns';
import type { Filters } from './models';
import {
  labelCreateService,
  labelServices,
  labelServicesDescription,
  labelWelcomeToServices
} from './translatedLabels';
import useServices from './useServices';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  defaultSortField,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

// The form sections are not built yet: a row opens an empty panel.
const form: Form = {
  defaultValues: {},
  groups: [],
  inputs: [],
  validationSchema: object()
};

const Services = () => {
  const { t } = useTranslation();

  const userPermissions = useAtomValue(userPermissionsAtom);
  const canEdit = !!userPermissions?.configuration_service_write;

  const { columns } = useColumns();
  const { api, filtersConfiguration } = useServices();

  const actions: Actions = useMemo(
    () => ({
      delete: () => canEdit,
      duplicate: () => canEdit,
      edit: canEdit,
      enableDisable: () => canEdit,
      massive: canEdit,
      // Keeps the status toggle on screen, disabled, for a read-only user.
      rowActionsWithoutWriteAccess: true,
      viewDetails: false
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
      defaultSortField={defaultSortField}
      filtersAtom={filtersAtom}
      filtersAtomKey={filtersAtomKey}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      // The default width fits neither five filters nor the names the
      // autocomplete holds.
      filtersPanelWidth={60}
      form={form}
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
