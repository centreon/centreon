import { useMemo, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Divider, Typography } from '@mui/material';
import FormControlLabel from '@mui/material/FormControlLabel';
import Switch from '@mui/material/Switch';

import { Criteria, CriteriaDisplayProps } from '../Criterias/models';
import { setCriteriaAndNewFilterDerivedAtom } from '../filterAtoms';

import MemoizedPoller from './MemoizedPoller';
import MemoizedState from './MemoizedState';
import BasicFilter from './basicFilter';
import {
  displayActionsAtom,
  displayInformationFilterAtom
} from './basicFilter/atoms';
import SectionWrapper from './basicFilter/sections';
import { useStyles } from './criterias.style';
import ExtendedFilter from './extendedFilter';
import {
  BasicCriteria,
  BuildDataByCategoryFilter,
  CategoryFilter,
  ChangedCriteriaParams,
  Data,
  DataByCategoryFilter,
  ExtendedCriteria
} from './model';
import { handleDataByCategoryFilter, mergeArraysByField } from './utils';
import { advancedModeLabel } from './translatedLabels';

export { CheckboxGroup } from '@centreon/ui';

interface Criterias {
  actions: JSX.Element;
  data: Data;
}

const CriteriasNewInterface = ({ data, actions }: Criterias): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);

  const displayActions = useAtomValue(displayActionsAtom);

  const setCriteriaAndNewFilter = useSetAtom(
    setCriteriaAndNewFilterDerivedAtom
  );

  const setDisplayInformationFilter = useSetAtom(displayInformationFilterAtom);

  const { newSelectableCriterias: buildCriterias, selectableCriterias } = data;

  const changeCriteria = ({
    updatedValue,
    filterName,
    search_data
  }: ChangedCriteriaParams): void => {
    const parameters = {
      name: filterName,
      value: updatedValue
    };

    setCriteriaAndNewFilter(
      search_data ? { ...parameters, search_data } : parameters
    );
  };

  const controlFilterInterface = (event): void => {
    setOpen(event.target.checked);
    if (!event.target.checked) {
      return;
    }
    setDisplayInformationFilter(false);
  };

  const buildDataByCategoryFilter = ({
    CriteriaType,
    selectableCriteria,
    buildedCriteria
  }: BuildDataByCategoryFilter): Array<CriteriaDisplayProps & Criteria> => {
    const dataInteraction = selectableCriteria.filter((item) =>
      Object.values(CriteriaType).includes(item.name)
    );

    const dataOfBuild = Object.keys(buildedCriteria).map((item) => {
      if (!Object.values(CriteriaType).includes(item)) {
        return null;
      }

      return { ...buildCriterias[item], name: item };
    });

    return mergeArraysByField({
      firstArray: dataInteraction,
      mergeBy: 'name',
      secondArray: dataOfBuild
    }) as Array<CriteriaDisplayProps & Criteria>;
  };

  const getDataByCategoryFilter = ({
    categoryFilter,
    selectableCriteria,
    buildedCriteria
  }: DataByCategoryFilter): Array<Criteria & CriteriaDisplayProps> => {
    const criteriaType =
      categoryFilter === CategoryFilter.BasicFilter
        ? Object.values(BasicCriteria)
        : Object.values(ExtendedCriteria);
    const dataByCategory = buildDataByCategoryFilter({
      CriteriaType: criteriaType,
      buildedCriteria,
      selectableCriteria
    });

    return handleDataByCategoryFilter({
      data: dataByCategory,
      fieldToUpdate: 'options',
      filter: categoryFilter
    });
  };

  const basicData = useMemo(() => {
    return getDataByCategoryFilter({
      buildedCriteria: buildCriterias,
      categoryFilter: CategoryFilter.BasicFilter,
      selectableCriteria: selectableCriterias
    });
  }, [selectableCriterias, buildCriterias]);

  const extendedData = useMemo(() => {
    return getDataByCategoryFilter({
      buildedCriteria: buildCriterias,
      categoryFilter: CategoryFilter.ExtendedFilter,
      selectableCriteria: selectableCriterias
    });
  }, [selectableCriterias, buildCriterias]);

  return (
    <>
      <div className={cx(classes.small, { [classes.extended]: open })}>
        <BasicFilter
          poller={
            <MemoizedPoller
              basicData={basicData}
              changeCriteria={changeCriteria}
            />
          }
          sections={
            <SectionWrapper
              basicData={basicData}
              changeCriteria={changeCriteria}
            />
          }
          state={
            <MemoizedState
              basicData={basicData}
              changeCriteria={changeCriteria}
            />
          }
        />

        {open && (
          <>
            <Divider
              flexItem
              className={classes.bridge}
              orientation="vertical"
              variant="middle"
            />
            <ExtendedFilter
              changeCriteria={changeCriteria}
              data={extendedData}
            />
          </>
        )}
      </div>

      <Divider className={classes.footer} />

      {displayActions && (
        <div className={classes.formControlContainer}>
          <FormControlLabel
            control={
              <Switch
                checked={open}
                inputProps={{ 'aria-label': 'controlled' }}
                size="small"
                onChange={controlFilterInterface}
              />
            }
            label={
              <Typography variant="body2">{t(advancedModeLabel)}</Typography>
            }
          />
          {actions}
        </div>
      )}
    </>
  );
};

export default CriteriasNewInterface;
