import {
  RocketLaunchOutlined as DeployIcon,
  EditNoteOutlined as MassChangeIcon,
  Grain as ServiceIcon
} from '@mui/icons-material';

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
import { type Actions, ResourceType } from '../models';
import { useDefaultPoller, useDeployServices } from './api';
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
  labelCreateHost,
  labelDeployServices,
  labelGoToServices,
  labelHosts,
  labelMassChange,
  labelWelcomeToHosts
} from './translatedLabels';
import useHosts from './useHosts';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  filtersAtomKey,
  filtersInitialValues,
  getHostServicesUrl
} from './utils';

const Hosts = () => {
  const { t } = useTranslation();

  const userPermissions = useAtomValue(userPermissionsAtom);
  // Read once and handed to both hooks: the fields and the rules that guard
  // them have to agree on the platform.
  const isCloudPlatform = !!useAtomValue(platformFeaturesAtom)?.isCloudPlatform;
  const isAdditiveInheritanceEnabled = useAtomValue(
    isAdditiveInheritanceEnabledAtom
  );
  const canEdit = !!userPermissions?.configuration_host_write;

  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs({
    canEdit,
    isAdditiveInheritanceEnabled,
    isCloudPlatform
  });
  const { validationSchema } = useValidationSchema({ isCloudPlatform });
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

  const { api, filtersConfiguration } = useHosts({
    isAdditiveInheritanceEnabled,
    isCloudPlatform
  });
  // Only a user who may create reads the default; the edit form opens on the
  // host's own poller whatever this holds.
  const defaultPoller = useDefaultPoller({ enabled: canEdit });
  const defaultValues = useMemo(
    () => ({
      ...getDefaultValues({ isCloudPlatform }),
      ...(defaultPoller && { poller: defaultPoller })
    }),
    [isCloudPlatform, defaultPoller]
  );
  const { deployServices } = useDeployServices();

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
        },
        {
          dataTestId: 'deploy-services',
          Icon: DeployIcon,
          label: labelDeployServices,
          onClick: deployServices
        }
      ],
      // A read action, so it survives the loss of write access.
      rowActions: [
        {
          dataTestId: ({ id }) => `go-to-services_${id}`,
          Icon: ServiceIcon,
          label: labelGoToServices,
          onClick: ({ name }) => {
            window.open(getHostServicesUrl(name as string), '_self');
          }
        }
      ],
      rowActionsWithoutWriteAccess: true,
      viewDetails: true
    }),
    [canEdit, deployServices, setFormState]
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
      // The default width fits neither five filters nor the names the
      // autocompletes hold.
      filtersPanelWidth={60}
      form={{ defaultValues, groups, inputs, massChange, validationSchema }}
      formVariant="panel"
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      labels={{
        title: t(labelHosts),
        welcomePage: {
          actions: {
            create: t(labelCreateHost)
          },
          title: t(labelWelcomeToHosts)
        }
      }}
      resourceType={ResourceType.Host}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
    />
  );
};

export default Hosts;
