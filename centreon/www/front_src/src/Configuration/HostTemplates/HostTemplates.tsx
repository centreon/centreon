import { EditNoteOutlined as MassChangeIcon } from '@mui/icons-material';

import {
  isAdditiveInheritanceEnabledAtom,
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { useAtomValue, useSetAtom } from 'jotai';
import { type ReactElement, useMemo } from 'react';
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
  useFormInputs,
  useValidationSchema
} from './Form';
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
  filtersInitialValues,
  isUnlocked
} from './utils';

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
  const massChange = useMemo(() => {
    const context = {
      isAdditiveInheritanceEnabled,
      isCloudPlatform,
      isMassChange: true
    };

    return {
      adapter: getMassChangeAdapter(context),
      defaultValues: getMassChangeDefaultValues(context),
      groups: massChangeGroups,
      inputs: massChangeInputs,
      validationSchema: massChangeValidationSchema
    };
  }, [
    isAdditiveInheritanceEnabled,
    isCloudPlatform,
    massChangeGroups,
    massChangeInputs,
    massChangeValidationSchema
  ]);
  const setFormState = useSetAtom(formStateAtom);

  const actions: Actions = useMemo(
    () => ({
      delete: (row) => canEdit && isUnlocked(row),
      duplicate: (row) => canEdit && isUnlocked(row),
      edit: canEdit,
      editable: isUnlocked,
      massive: canEdit && { delete: true, duplicate: true },
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
      selectable: isUnlocked,
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
      filtersAtom={filtersAtom}
      filtersAtomKey={filtersAtomKey}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      filtersPanelWidth="full"
      form={{ defaultValues, groups, inputs, massChange, validationSchema }}
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
