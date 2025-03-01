import { useAtom } from 'jotai';
import { isEmpty, isNil, not } from 'ramda';
import { useEffect, useMemo } from 'react';
import { ConfigurationBase } from '../models';
import { configurationAtom, filtersAtom } from './atoms';

import Page from './Page';
import { filtersAtomKey } from './constants';

const Base = ({
  columns,
  resourceType,
  form,
  api,
  filtersConfiguration,
  filtersInitialValues,
  defaultSelectedColumnIds
}: ConfigurationBase): JSX.Element => {
  const [configuration, setConfiguration] = useAtom(configurationAtom);
  const [filters, setFilters] = useAtom(filtersAtom);

  useEffect(() => {
    setConfiguration({
      resourceType,
      api,
      filtersConfiguration,
      filtersInitialValues,
      defaultSelectedColumnIds
    });

    if (isNil(localStorage.getItem(filtersAtomKey))) {
      setFilters(filtersInitialValues);
    }
  }, [
    setConfiguration,
    api,
    filtersConfiguration,
    defaultSelectedColumnIds,
    filtersInitialValues
  ]);

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
