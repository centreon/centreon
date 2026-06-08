import { useMemoComponent } from '@centreon/ui';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { isNil, pipe, reject, sortBy } from 'ramda';
import { useState } from 'react';
import { makeStyles } from 'tss-react/mui';

import CriteriasNewInterface from '../criteriasNewInterface';
import Actions from '../criteriasNewInterface/actions';
import Save from '../criteriasNewInterface/actions/Save';
import {
  displayActionsAtom,
  selectedStatusByResourceTypeAtom
} from '../criteriasNewInterface/basicFilter/atoms';
import {
  applyCurrentFilterDerivedAtom,
  clearFilterDerivedAtom,
  currentFilterAtom,
  customFiltersAtom,
  filterByInstalledModulesWithParsedSearchDerivedAtom,
  filterWithParsedSearchDerivedAtom,
  isCriteriasPanelOpenAtom
} from '../filterAtoms';
import useFilterByModule from '../useFilterByModule';
import {
  CriteriaDisplayProps,
  Criteria as CriteriaModel,
  SearchDataPropsCriterias
} from './models';
import SaveActions from './SaveActions';
import { criteriaNameSortOrder } from './searchQueryLanguage/models';

const useStyles = makeStyles()((theme) => ({
  panel: {
    backgroundColor: theme.palette.background.paper,
    border: `1px solid ${theme.palette.divider}`,
    borderRadius: theme.spacing(1.5),
    boxShadow: theme.shadows[2],
    marginTop: theme.spacing(1),
    padding: theme.spacing(2, 2.5),
    width: '100%'
  }
}));

interface Props {
  searchData: SearchDataPropsCriterias;
}

const CriteriasContent = ({ searchData }: Props): JSX.Element => {
  const { classes } = useStyles();
  const [isCreatingFilter, setIsCreatingFilter] = useState(false);

  const { newCriteriaValueName, newSelectableCriterias } = useFilterByModule();

  const filterByInstalledModulesWithParsedSearch = useAtomValue(
    filterByInstalledModulesWithParsedSearchDerivedAtom
  );

  const setDisplayActions = useSetAtom(displayActionsAtom);
  const setSelectedStatusByResourceType = useSetAtom(
    selectedStatusByResourceTypeAtom
  );
  const clearFilter = useSetAtom(clearFilterDerivedAtom);
  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);
  const [isCriteriasPanelOpen, setIsCriteriasPanelOpen] = useAtom(
    isCriteriasPanelOpenAtom
  );

  const getSelectableCriterias = (): Array<CriteriaModel> => {
    const criteriasValue = filterByInstalledModulesWithParsedSearch({
      criteriaName: newCriteriaValueName
    });

    const criterias = sortBy(
      ({ name }) => (criteriaNameSortOrder as Record<string, number>)[name],
      criteriasValue.criterias
    );

    return reject(isNonSelectableCriteria)(criterias);
  };

  const getSelectableCriteriaByName = (name: string): CriteriaDisplayProps =>
    newSelectableCriterias[name];

  const isNonSelectableCriteria = (criteria: CriteriaModel): boolean => {
    return pipe(
      ({ name }) => name,
      getSelectableCriteriaByName,
      isNil
    )(criteria);
  };

  const clearFilters = (): void => {
    clearFilter();
    setSelectedStatusByResourceType(null);
  };

  const getIsCreateFilter = (boolean: boolean): void => {
    setIsCreatingFilter(boolean);
  };

  const closePanel = (): void => {
    setDisplayActions(false);
    setIsCriteriasPanelOpen(false);
  };

  const search = (): void => {
    applyCurrentFilter();
    closePanel();
  };

  return (
    <>
      {isCriteriasPanelOpen && (
        <div className={classes.panel} data-testid="advancedFiltersPanel">
          <CriteriasNewInterface
            actions={
              <Actions
                onClear={clearFilters}
                onSearch={search}
                save={
                  <Save
                    closePopover={closePanel}
                    getIsCreateFilter={getIsCreateFilter}
                  />
                }
              />
            }
            data={{
              newSelectableCriterias,
              searchData,
              selectableCriterias: getSelectableCriterias()
            }}
          />
        </div>
      )}

      <SaveActions
        dataCreateFilter={{ isCreatingFilter, setIsCreatingFilter }}
      />
    </>
  );
};

const Criterias = ({ searchData }: Props): JSX.Element => {
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom
  );
  const isCriteriasPanelOpen = useAtomValue(isCriteriasPanelOpenAtom);
  const customFilters = useAtomValue(customFiltersAtom);
  const currentFilter = useAtomValue(currentFilterAtom);

  return useMemoComponent({
    Component: <CriteriasContent searchData={searchData} />,
    memoProps: [
      filterWithParsedSearch,
      isCriteriasPanelOpen,
      customFilters,
      currentFilter,
      searchData.search
    ]
  });
};

export default Criterias;
