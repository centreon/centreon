import { useAtom, useSetAtom } from 'jotai';
import { isEmpty, isNil, not } from 'ramda';
import { JSX, useEffect, useMemo } from 'react';

import { ConfigurationBase } from '../models';
import { configurationAtom } from './atoms';
import Page from './Page';

const Base = <TFilters,>({
  columns,
  resourceType,
  form,
  api,
  filtersConfiguration,
  filtersInitialValues,
  defaultSelectedColumnIds,
  actions,
  labels,
  selectedColumnIdsAtom,
  columnsAtomKey,
  filtersAtom,
  filtersAtomKey,
  isWelcomePageDisplayedAtom
}: ConfigurationBase<TFilters>): JSX.Element => {
  const [configuration, setConfiguration] = useAtom(configurationAtom);
  const [filters, setFilters] = useAtom(filtersAtom);
  const setSelectedColumnIds = useSetAtom(selectedColumnIdsAtom);

  useEffect(() => {
    setConfiguration({
      actions,
      api,
      defaultSelectedColumnIds,
      filtersConfiguration,
      filtersInitialValues,
      resourceType
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
    <Page<TFilters>
      actions={actions}
      columns={columns}
      filtersAtom={filtersAtom}
      filtersAtomKey={filtersAtomKey}
      form={form}
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      labels={labels}
      resourceType={resourceType}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
    />
  );
};

export default Base;
