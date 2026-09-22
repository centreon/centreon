import {
  RocketLaunchOutlined as DeployIcon,
  Grain as ServiceIcon
} from '@mui/icons-material';

import { userPermissionsAtom } from '@centreon/ui-context';

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
import { defaultValues, useFormInputs, useValidationSchema } from './Form';
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
  const canEdit = !!userPermissions?.configuration_host_write;

  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs();
  const { validationSchema } = useValidationSchema();

  const { api, filtersConfiguration } = useHosts();
  const { deployServices } = useDeployServices();

  const actions: Actions = useMemo(
    () => ({
      delete: () => canEdit,
      edit: canEdit,
      enableDisable: () => canEdit,
      // Duplicate is left out entirely: hosts have no duplicate endpoint at all,
      // single or bulk, so the icon and the menu entry would only ever 404. Bulk
      // delete is missing too, so More actions offers only what works — enable,
      // disable and deploy services. Restoring them is one line here once the
      // endpoints ship; the mocked specs are already written against them.
      massive: canEdit && { disable: true, enable: true },
      massiveActions: [
        {
          dataTestId: 'deploy-services',
          Icon: DeployIcon,
          label: labelDeployServices,
          onClick: deployServices
        }
      ],
      // Reaching a host's services is a read action: it survives the loss of
      // write access, unlike duplicate and delete.
      rowActions: [
        {
          dataTestId: ({ id }) => `go-to-services_${id}`,
          // The same icon the top counter uses for services, as the spec asks.
          Icon: ServiceIcon,
          label: labelGoToServices,
          // Same tab: the spec calls this a redirection to the services
          // listing, as the legacy hosts listing does.
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
      form={{ defaultValues, groups, inputs, validationSchema }}
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
