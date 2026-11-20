// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { isEmpty, isNil, not } from 'ramda';
import { JSX, useEffect, useMemo } from 'react';

import { ConfigurationBase } from '../models';
import { configurationAtom } from './atoms';
import {
  sortFieldAtom,
  sortOrderAtom,
  sortsByListingAtom
} from './Listing/atoms';
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
  isWelcomePageDisplayedAtom,
  navbar,
  filtersPanelWidth,
  formVariant,
  formPanelWidth,
  defaultSortField,
  getRowId
}: ConfigurationBase<TFilters>): JSX.Element => {
  const [configuration, setConfiguration] = useAtom(configurationAtom);
  const [filters, setFilters] = useAtom(filtersAtom);
  const setSelectedColumnIds = useSetAtom(selectedColumnIdsAtom);
  const setSortField = useSetAtom(sortFieldAtom);
  const setSortOrder = useSetAtom(sortOrderAtom);
  const sortsByListing = useAtomValue(sortsByListingAtom);

  // The sort atoms are shared by every listing: each page opens on the sort
  // it was left on, never on another listing's.
  useEffect(() => {
    const sort = sortsByListing[resourceType];

    setSortField(sort?.field ?? defaultSortField ?? 'name');
    setSortOrder(sort?.order ?? 'asc');
  }, [resourceType, defaultSortField]);

  useEffect(() => {
    setConfiguration({
      actions,
      api,
      defaultSelectedColumnIds,
      filtersConfiguration,
      filtersInitialValues,
      filtersPanelWidth,
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
    filtersPanelWidth,
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
      formPanelWidth={formPanelWidth}
      formVariant={formVariant}
      getRowId={getRowId}
      isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
      labels={labels}
      navbar={navbar}
      resourceType={resourceType}
      selectedColumnIdsAtom={selectedColumnIdsAtom}
    />
  );
};

export default Base;
