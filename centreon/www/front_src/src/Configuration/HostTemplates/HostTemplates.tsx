import {
  isAdditiveInheritanceEnabledAtom,
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { type ReactElement, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import { type Actions, type ResourceRow, ResourceType } from '../models';
import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import useColumns from './Columns/useColumns';
import { getDefaultValues, useFormInputs, useValidationSchema } from './Form';
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

// A locked template cannot be selected, duplicated or deleted.
const isUnlocked = (row?: ResourceRow): boolean => !row?.isLocked;

const HostTemplates = (): ReactElement => {
  const { t } = useTranslation();

  const userPermissions = useAtomValue(userPermissionsAtom);
  // Read once and handed to every hook: the fields and the rules that guard
  // them have to agree on the platform.
  const isCloudPlatform = !!useAtomValue(platformFeaturesAtom)?.isCloudPlatform;
  const isAdditiveInheritanceEnabled = useAtomValue(
    isAdditiveInheritanceEnabledAtom
  );
  const canEdit = !!userPermissions?.configuration_host_template_write;

  const { columns } = useColumns();
  const { api, filtersConfiguration } = useHostTemplates({
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

  const actions: Actions = useMemo(
    () => ({
      delete: (row) => canEdit && isUnlocked(row),
      duplicate: (row) => canEdit && isUnlocked(row),
      edit: canEdit,
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
      filtersPanelWidth="full"
      form={{ defaultValues, groups, inputs, validationSchema }}
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
