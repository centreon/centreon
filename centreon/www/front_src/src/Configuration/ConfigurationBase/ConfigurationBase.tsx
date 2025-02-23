import { useAtomValue } from 'jotai';
import { isEmpty, not } from 'ramda';
import { useMemo } from 'react';
import { configurationAtom, filtersAtom } from '../atoms';
import { ConfigurationBase } from '../models';

import Page from './Page';

const Base = ({
  columns,
  resourceType,
  form
}: ConfigurationBase): JSX.Element => {
  const configuration = useAtomValue(configurationAtom);
  const filters = useAtomValue(filtersAtom);

  const isConfigurationValid = useMemo(
    () =>
      configuration?.api?.endpoints &&
      configuration?.resourceType &&
      configuration?.filtersConfiguration &&
      !isEmpty(configuration?.defaultSelectedColumnIds) &&
      !isEmpty(configuration?.filtersInitialValues) &&
      !isEmpty(filters),
    [configuration, filters]
  ) as boolean;

  if (not(isConfigurationValid)) {
    return <div />;
  }

  return <Page columns={columns} resourceType={resourceType} form={form} />;
};

export default Base;
