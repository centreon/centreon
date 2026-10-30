import { userPermissionsAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { type JSX, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { object } from 'yup';

import ConfigurationBase from '../ConfigurationBase';
import { type Actions, type ResourceRow, ResourceType } from '../models';
import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import useColumns from './Columns/useColumns';
import type { Filters } from './models';
import {
  labelCreateHostTemplate,
  labelHostTemplates,
  labelWelcomeToHostTemplates
} from './translatedLabels';
import useHostTemplates from './useHostTemplates';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

// TODO MON-213546 form sub-task: the create, edit and details form.
const form = {
  defaultValues: {},
  groups: [],
  inputs: [],
  validationSchema: object()
};

// A locked template is read-only: no selection, no duplicate, no delete.
const isUnlocked = (row?: ResourceRow): boolean => !row?.isLocked;

const HostTemplates = (): JSX.Element => {
  const { t } = useTranslation();

  const userPermissions = useAtomValue(userPermissionsAtom);
  const canEdit = !!userPermissions?.configuration_host_template_write;

  const { columns } = useColumns();
  const { api, filtersConfiguration } = useHostTemplates();

  const actions: Actions = useMemo(
    () => ({
      delete: (row) => canEdit && isUnlocked(row),
      duplicate: (row) => canEdit && isUnlocked(row),
      edit: canEdit,
      // Host templates cannot be enabled or disabled.
      massive: canEdit && { delete: true, duplicate: true },
      selectable: isUnlocked,
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
      form={form}
      formVariant="panel"
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      labels={{
        title: t(labelHostTemplates),
        welcomePage: {
          actions: {
            create: t(labelCreateHostTemplate)
          },
          title: t(labelWelcomeToHostTemplates)
        }
      }}
      resourceType={ResourceType.HostTemplate}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
    />
  );
};

export default HostTemplates;
