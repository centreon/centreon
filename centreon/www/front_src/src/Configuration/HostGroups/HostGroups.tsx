import { userPermissionsAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';

import ConfigurationBase from '../ConfigurationBase';
import useColumns from './Columns/useColumns';

import { defaultValues, useFormInputs, useValidationSchema } from './Form';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

import { useTranslation } from 'react-i18next';
import { ResourceType } from '../models';

import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import useHostGroups from './useHostGroups';

import { Filters } from './models';
import {
  labelAddHostGroup,
  labelHostGroups,
  labelWelcomeToHostGroups
} from './translatedLabels';

const HostGroups = () => {
  const { t } = useTranslation();

  const userPermissions = useAtomValue(userPermissionsAtom);
  const canEdit = !!userPermissions?.configuration_host_group_write;

  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs({ canEdit });
  const { validationSchema } = useValidationSchema();

  const { api, filtersConfiguration } = useHostGroups();

  return (
    <ConfigurationBase<Filters>
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      filtersAtomKey={filtersAtomKey}
      filtersAtom={filtersAtom}
      columnsAtomKey={columnsAtomKey}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
      columns={columns}
      resourceType={ResourceType.HostGroup}
      form={{ inputs, groups, validationSchema, defaultValues }}
      api={api}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      actions={{
        massive: true,
        enableDisable: true,
        delete: true,
        duplicate: true,
        edit: canEdit,
        viewDetails: true
      }}
      labels={{
        title: t(labelHostGroups),
        welcomePage: {
          title: t(labelWelcomeToHostGroups),
          actions: {
            create: t(labelAddHostGroup)
          }
        }
      }}
    />
  );
};

export default HostGroups;
