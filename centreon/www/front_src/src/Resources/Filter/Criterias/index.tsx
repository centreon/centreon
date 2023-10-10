import { useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { isNil, pipe, reject, sortBy } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import TuneIcon from '@mui/icons-material/Tune';
import { Grid } from '@mui/material';

import { PopoverMenu } from '@centreon/ui';

import { hoveredNavigationItemsAtom } from '../../../Navigation/Sidebar/sideBarAtoms';
import { labelSearchOptions } from '../../translatedLabels';
import useActionFilter from '../Save/useActionFilter';
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
  filterByInstalledModulesWithParsedSearchDerivedAtom,
  filterWithParsedSearchDerivedAtom
} from '../filterAtoms';
import useFilterByModule from '../useFilterByModule';

import SaveActions from './SaveActions';
import {
  CriteriaDisplayProps,
  Criteria as CriteriaModel,
  PopoverData
} from './models';
import { criteriaNameSortOrder } from './searchQueryLanguage/models';

interface Styles {
  display: boolean;
}

const useStyles = makeStyles<Styles>()((theme, { display }) => ({
  container: {
    display: !display ? 'none' : 'flex',
    padding: theme.spacing(2)
  },
  searchButton: {
    marginTop: theme.spacing(1)
  }
}));

interface Props {
  display?: boolean;
}

const CriteriasContent = ({ display = false }: Props): JSX.Element => {
  const { classes } = useStyles({ display });
  const { t } = useTranslation();
  const [isCreateFilter, setIsCreateFilter] = useState(false);
  const [isUpdateFilter, setIsUpdateFilter] = useState(false);
  const [popoverData, setPopoverData] = useState<PopoverData | undefined>();

  const hoveredNavigationItem = useAtomValue(hoveredNavigationItemsAtom);
  const canOpenPopover = isNil(hoveredNavigationItem);

  const { newCriteriaValueName, newSelectableCriterias } = useFilterByModule();

  const { canSaveFilter, loadFiltersAndUpdateCurrent, isNewFilter } =
    useActionFilter();

  const filterByInstalledModulesWithParsedSearch = useAtomValue(
    filterByInstalledModulesWithParsedSearchDerivedAtom
  );

  const setDisplayActions = useSetAtom(displayActionsAtom);

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

  const isNonSelectableCriteria = (criteria: CriteriaModel): boolean =>
    pipe(({ name }) => name, getSelectableCriteriaByName, isNil)(criteria);

  const applyCurrentFilter = useSetAtom(applyCurrentFilterDerivedAtom);
  const setSelectedStatusByResourceType = useSetAtom(
    selectedStatusByResourceTypeAtom
  );
  const clearFilter = useSetAtom(clearFilterDerivedAtom);

  const clearFilters = (): void => {
    clearFilter();
    setSelectedStatusByResourceType(null);
  };

  const getIsCreateFilter = (boolean: boolean): void => {
    setIsCreateFilter(boolean);
  };

  const getIsUpdateFilter = (boolean: boolean): void => {
    setIsUpdateFilter(boolean);
  };

  const getPopoverData = (data: PopoverData): void => {
    const { anchorEl } = data;
    setPopoverData(data);
    if (!anchorEl) {
      setDisplayActions(false);
    }
  };

  return (
    <>
      <PopoverMenu
        canOpen={canOpenPopover}
        getPopoverData={getPopoverData}
        icon={<TuneIcon fontSize="small" />}
        popperPlacement="bottom-start"
        popperProps={{
          modifiers: [
            {
              name: 'offset',
              options: {
                offset: [0, 16]
              }
            }
          ]
        }}
        title={t(labelSearchOptions) as string}
        onClose={applyCurrentFilter}
      >
        {(): JSX.Element => (
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
                      canSaveFilter={canSaveFilter}
                      getIsCreateFilter={getIsCreateFilter}
                      getIsUpdateFilter={getIsUpdateFilter}
                      isNewFilter={isNewFilter}
                      popoverData={popoverData}
                    />
                  }
                  onClear={clearFilters}
                  onSearch={applyCurrentFilter}
                />
              }
              data={{
                newSelectableCriterias,
                selectableCriterias: getSelectableCriterias()
              }}
            />
          </Grid>
        )}
      </PopoverMenu>

      <SaveActions
        dataCreateFilter={{ isCreateFilter, setIsCreateFilter }}
        dataUpdateFilter={{ isUpdateFilter, setIsUpdateFilter }}
        loadFiltersAndUpdateCurrent={loadFiltersAndUpdateCurrent}
      />
    </>
  );
};

const Criterias = (): JSX.Element => {
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom
  );
  const display = useAtomValue(displayActionsAtom);
  // const filters = useAtomValue(filtersDerivedAtom);
  // const currentFilter = useAtomValue(currentFilterAtom);

  // return useMemoComponent({
  //   Component: <CriteriasContent />,
  //   memoProps: [filterWithParsedSearch]
  // });

  return <CriteriasContent display={display} />;
};

export default Criterias;
