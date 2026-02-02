import { userPermissionsAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import { ResourceType } from '../models';
import useColumns from './Columns/useColumns';
import { defaultValues, useFormInputs, useValidationSchema } from './Form';
import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import { Filters } from './models';
import {
  labelAddHostGroup,
  labelHostGroups,
  labelWelcomeToHostGroups
} from './translatedLabels';
import useHostGroups from './useHostGroups';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

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
      actions={{
        delete: () => true,
        duplicate: true,
        edit: canEdit,
        enableDisable: true,
        massive: true,
        viewDetails: true
      }}
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
        title: t(labelHostGroups),
        welcomePage: {
          actions: {
            create: t(labelAddHostGroup)
          },
          title: t(labelWelcomeToHostGroups)
        }
      }}
      resourceType={ResourceType.HostGroup}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
    />
  );
};

export default HostGroups;
