import { useTranslation } from 'react-i18next';

import ConfigurationBase from '../ConfigurationBase';
import { ResourceType } from '../models';
import {
  filtersAtom,
  isWelcomePageDisplayedAtom,
  selectedColumnIdsAtom
} from './atoms';
import useColumns from './Columns/useColumns';
import { defaultValues, useFormInputs, useValidationSchema } from './Form';
import type { Filters } from './models';
import {
  labelAddHost,
  labelHosts,
  labelWelcomeToHosts
} from './translatedLabels';
import useHosts from './useHosts';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

const Hosts = () => {
  const { t } = useTranslation();

  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs();
  const { validationSchema } = useValidationSchema();

  const { api, filtersConfiguration } = useHosts();

  return (
    <ConfigurationBase<Filters>
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
            create: t(labelAddHost)
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
