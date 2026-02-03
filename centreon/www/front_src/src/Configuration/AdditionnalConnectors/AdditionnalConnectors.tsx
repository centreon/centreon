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
  labelAddAdditionalConfigurations,
  labelAdditionalConnectorConfiguration,
  labelPageDescription,
  labelWelcomeToAdditionalConfigurations
} from './translatedLabels';
import useAdditionnalConnectors from './useAdditionnalConnectors';
import {
  columnsAtomKey,
  defaultSelectedColumnIds,
  filtersAtomKey,
  filtersInitialValues
} from './utils';

const AdditionnalConnectors = () => {
  const { t } = useTranslation();

  const { columns } = useColumns();
  const { groups, inputs } = useFormInputs();
  const { validationSchema } = useValidationSchema();

  const { api, filtersConfiguration } = useAdditionnalConnectors();

  return (
    <ConfigurationBase<Filters>
      actions={{
        delete: () => true,
        edit: true
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
        title: t(labelAdditionalConnectorConfiguration),
        welcomePage: {
          actions: {
            create: t(labelAddAdditionalConfigurations)
          },
          description: t(labelPageDescription),
          title: t(labelWelcomeToAdditionalConfigurations)
        }
      }}
      resourceType={ResourceType.AdditionalConfiguration}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
    />
  );
};

export default AdditionnalConnectors;
