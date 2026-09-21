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
  labelWelcomeToHosts,
  labelWelcomeToHostsDescription
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
      /**
       * `edit` drives `canCreate` on the empty state, so it is what surfaces the
       * create button the empty state requires. Hardcoded because there is no host
       * write permission exposed by `FindUserPermissions` to gate it on.
       *
       * It also mounts the shared edit Modal and the row-click handler, neither of
       * which can work here: no `getOne` endpoint and no `adapter` are declared.
       * Both are unreachable while the page is dark-launched, and the form ticket
       * replaces that Modal with a side panel, so they are left as-is rather than
       * worked around.
       */
      actions={{ edit: true }}
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
          description: t(labelWelcomeToHostsDescription),
          title: t(labelWelcomeToHosts)
        }
      }}
      resourceType={ResourceType.Host}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
    />
  );
};

export default Hosts;
