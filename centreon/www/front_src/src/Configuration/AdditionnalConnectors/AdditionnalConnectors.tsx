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
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      columnsAtomKey={columnsAtomKey}
      filtersAtomKey={filtersAtomKey}
      filtersAtom={filtersAtom}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
      columns={columns}
      resourceType={ResourceType.AdditionalConfigurations}
      form={{ inputs, groups, validationSchema, defaultValues }}
      api={api}
      filtersConfiguration={filtersConfiguration}
      filtersInitialValues={filtersInitialValues}
      defaultSelectedColumnIds={defaultSelectedColumnIds}
      actions={{
        delete: true,
        edit: true
      }}
      labels={{
        title: t(labelAdditionalConnectorConfiguration),
        welcomePage: {
          title: t(labelWelcomeToAdditionalConfigurations),
          description: t(labelPageDescription),
          actions: {
            create: t(labelAddAdditionalConfigurations)
          }
        }
      }}
    />
  );
};

export default AdditionnalConnectors;
