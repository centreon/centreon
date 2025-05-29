import { useAtom, useSetAtom } from 'jotai';
import { isEmpty, isNil, not } from 'ramda';
import { JSX, useEffect, useMemo } from 'react';
import { ConfigurationBase } from '../models';
import { configurationAtom, filtersAtom, selectedColumnIdsAtom } from './atoms';

import Page from './Page';
import { columnsAtomKey, filtersAtomKey } from './constants';

const Base = ({
  columns,
  resourceType,
  form,
  api,
  filtersConfiguration,
  filtersInitialValues,
  defaultSelectedColumnIds,
  actions
}: ConfigurationBase): JSX.Element => {
  const [configuration, setConfiguration] = useAtom(configurationAtom);
  const [filters, setFilters] = useAtom(filtersAtom);
  const setSelectedColumnIds = useSetAtom(selectedColumnIdsAtom);

  useEffect(() => {
    setConfiguration({
      resourceType,
      api,
      filtersConfiguration,
      filtersInitialValues,
      defaultSelectedColumnIds,
      actions
    });

    if (isNil(localStorage.getItem(filtersAtomKey))) {
      setFilters(filtersInitialValues);
    }

    if (isNil(localStorage.getItem(columnsAtomKey))) {
      setSelectedColumnIds(defaultSelectedColumnIds);
    }
  }, [
    setConfiguration,
    api,
    filtersConfiguration,
    defaultSelectedColumnIds,
    filtersInitialValues,
    actions
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

  return (
    <Page
      columns={columns}
      resourceType={resourceType}
      form={form}
      actions={actions}
    />
  );
};

export default Base;
