import {
  RocketLaunchOutlined as DeployIcon,
  Grain as ServiceIcon
} from '@mui/icons-material';

import {
  isAdditiveInheritanceEnabledAtom,
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import { type Actions, ResourceType } from '../models';
import { useDeployServices } from './api';
import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import useColumns from './Columns/useColumns';
import { getDefaultValues, useFormInputs, useValidationSchema } from './Form';
import type { Filters } from './models';
import {
  labelCreateHost,
  labelDeployServices,
  labelGoToServices,
  labelHosts,
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

  const { api, filtersConfiguration } = useHosts({
    isAdditiveInheritanceEnabled,
    isCloudPlatform
  });
  const defaultValues = useMemo(
    () => getDefaultValues({ isCloudPlatform }),
    [isCloudPlatform]
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
    [canEdit, deployServices]
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
      form={{ defaultValues, groups, inputs, validationSchema }}
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
