import {
  isAdditiveInheritanceEnabledAtom,
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import {
  getDefaultValues,
  useFormInputs,
  useValidationSchema
} from '../Hosts/Form';
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
  labelHostTemplatesDescription,
  labelWelcomeToHostTemplates
} from './translatedLabels';
import useHostTemplates from './useHostTemplates';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

// The server refuses to update or delete a template a monitoring connector
// owns, and legacy neither selects nor duplicates one.
const isLocked = (row?: ResourceRow): boolean => !!row?.isLocked;

const HostTemplates = () => {
  const { t } = useTranslation();

  const userPermissions = useAtomValue(userPermissionsAtom);
  const isCloudPlatform = !!useAtomValue(platformFeaturesAtom)?.isCloudPlatform;
  const isAdditiveInheritanceEnabled = useAtomValue(
    isAdditiveInheritanceEnabledAtom
  );
  const canEdit = !!userPermissions?.configuration_host_template_write;

  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs({
    canEdit,
    isAdditiveInheritanceEnabled,
    isCloudPlatform,
    isTemplate: true
  });
  const { validationSchema } = useValidationSchema({
    isCloudPlatform,
    isTemplate: true
  });

  const { api, filtersConfiguration } = useHostTemplates({
    isAdditiveInheritanceEnabled,
    isCloudPlatform
  });
  const defaultValues = useMemo(
    () => getDefaultValues({ isCloudPlatform, isTemplate: true }),
    [isCloudPlatform]
  );

  const actions: Actions = useMemo(
    () => ({
      delete: (row) => canEdit && !isLocked(row),
      duplicate: (row) => canEdit && !isLocked(row),
      edit: canEdit,
      isRowDisabled: isLocked,
      massive: canEdit && { delete: true, duplicate: true },
      // A locked template opens read-only, whoever opens it.
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
      form={{ defaultValues, groups, inputs, validationSchema }}
      formVariant="panel"
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      labels={{
        description: t(labelHostTemplatesDescription),
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
