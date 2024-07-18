import { useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { isNil, pipe, reject, sortBy } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import TuneIcon from '@mui/icons-material/Tune';
import { Grid } from '@mui/material';

import { PopoverMenu, useMemoComponent } from '@centreon/ui';

import { hoveredNavigationItemsAtom } from '../../../Navigation/Sidebar/sideBarAtoms';
import { labelSearchOptions } from '../../translatedLabels';
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

import SaveActions from './SaveActions';
import {
  CriteriaDisplayProps,
  Criteria as CriteriaModel,
  PopoverData,
  SearchDataPropsCriterias
} from './models';
import { criteriaNameSortOrder } from './searchQueryLanguage/models';

interface Styles {
  display: boolean;
}

const useStyles = makeStyles<Styles>()((theme, { display }) => ({
  container: {
    alignItems: 'center',
    display: !display ? 'none' : 'flex',
    marginTop: theme.spacing(1),
    padding: theme.spacing(2, 2, 2, 3)
  },
  searchButton: {
    marginTop: theme.spacing(1)
  }
}));

interface Props {
  display?: boolean;
  searchData: SearchDataPropsCriterias;
}

const CriteriasContent = ({
  display = false,
  searchData
}: Props): JSX.Element => {
  const { classes } = useStyles({ display });
  const { t } = useTranslation();
  const [isCreatingFilter, setIsCreatingFilter] = useState(false);

  const hoveredNavigationItem = useAtomValue(hoveredNavigationItemsAtom);

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
  const setIsCriteriasPanelOpen = useSetAtom(isCriteriasPanelOpenAtom);
  const canOpenPopover = isNil(hoveredNavigationItem);

  const getSelectableCriterias = (): Array<CriteriaModel> => {
    const criteriasValue = filterByInstalledModulesWithParsedSearch({
      criteriaName: newCriteriaValueName
    });

    const criterias = sortBy(
      ({ name }) => criteriaNameSortOrder[name],
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

  const getPopoverData = (data: PopoverData): void => {
    const { anchorEl } = data;
    if (anchorEl) {
      return;
    }
    setDisplayActions(false);
  };

  const open = (): void => {
    setIsCriteriasPanelOpen(true);
  };

  const onClose = (): void => {
    applyCurrentFilter();
    setIsCriteriasPanelOpen(false);
  };

  return (
    <>
      <PopoverMenu
        canOpen={canOpenPopover}
        dataTestId={labelSearchOptions}
        getPopoverData={getPopoverData}
        icon={<TuneIcon fontSize="small" />}
        popperPlacement="bottom-end"
        title={t(labelSearchOptions) as string}
        onClose={onClose}
        onOpen={open}
      >
        {({ close }): JSX.Element => {
          const closePopover = (): void => {
            setDisplayActions(false);
            close();
          };

          return (
            <Grid
              container
              alignItems="stretch"
              className={classes.container}
              direction="column"
              spacing={1}
            >
              <CriteriasNewInterface
                actions={
                  <Actions
                    save={
                      <Save
                        closePopover={closePopover}
                        getIsCreateFilter={getIsCreateFilter}
                      />
                    }
                    onClear={clearFilters}
                    onSearch={applyCurrentFilter}
                  />
                }
                data={{
                  newSelectableCriterias,
                  searchData,
                  selectableCriterias: getSelectableCriterias()
                }}
              />
            </Grid>
          );
        }}
      </PopoverMenu>

      <SaveActions
        dataCreateFilter={{ isCreatingFilter, setIsCreatingFilter }}
      />
    </>
  );
};

interface Props {
  searchData: SearchDataPropsCriterias;
}

const Criterias = ({ searchData }: Props): JSX.Element => {
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom
  );
  const display = useAtomValue(displayActionsAtom);
  const customFilters = useAtomValue(customFiltersAtom);
  const currentFilter = useAtomValue(currentFilterAtom);

  return useMemoComponent({
    Component: <CriteriasContent display={display} searchData={searchData} />,
    memoProps: [
      filterWithParsedSearch,
      display,
      customFilters,
      currentFilter,
      searchData.search
    ]
  });
};

export default Criterias;
