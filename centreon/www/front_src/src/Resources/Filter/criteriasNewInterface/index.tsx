import { useMemo, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';
import { Divider, Typography } from '@mui/material';

import { Button } from '@centreon/ui/components';

import { labelState, labelType } from '../../translatedLabels';
import { Criteria, CriteriaDisplayProps } from '../Criterias/models';
import { setCriteriaAndNewFilterDerivedAtom } from '../filterAtoms';

import MemoizedCheckBox from './MemoizedCheckBox';
import MemoizedPoller from './MemoizedPoller';
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
import {
  labelShowFewerFilters,
  labelShowMoreFilters
} from './translatedLabels';
import { mergeArraysByField } from './utils';

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

  const {
    newSelectableCriterias: buildCriterias,
    selectableCriterias,
    searchData
  } = data;

  const changeCriteria = ({
    updatedValue,
    filterName
  }: ChangedCriteriaParams): void => {
    const parameters = {
      name: filterName,
      value: updatedValue
    };

    setCriteriaAndNewFilter(parameters);
  };

  const controlFilterInterface = (): void => {
    setOpen((currentOpen) => {
      const newState = !currentOpen;
      if (newState) {
        setDisplayInformationFilter(false);
      }

      return newState;
    });
  };

  const buildDataByCategoryFilter = ({
    CriteriaType,
    selectableCriteria,
    builtCriteria
  }: BuildDataByCategoryFilter): Array<CriteriaDisplayProps & Criteria> => {
    const dataInteraction = selectableCriteria.filter((item) =>
      Object.values(CriteriaType).includes(item.name)
    );

    const dataOfBuild = Object.keys(builtCriteria).map((item) => {
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
    builtCriteria
  }: DataByCategoryFilter): Array<Criteria & CriteriaDisplayProps> => {
    const criteriaType =
      categoryFilter === CategoryFilter.BasicFilter
        ? Object.values(BasicCriteria)
        : Object.values(ExtendedCriteria);

    return buildDataByCategoryFilter({
      CriteriaType: criteriaType,
      builtCriteria,
      selectableCriteria
    });
  };

  const basicData = useMemo(() => {
    return getDataByCategoryFilter({
      builtCriteria: buildCriterias,
      categoryFilter: CategoryFilter.BasicFilter,
      selectableCriteria: selectableCriterias
    });
  }, [selectableCriterias, buildCriterias]);

  const extendedData = useMemo(() => {
    return getDataByCategoryFilter({
      builtCriteria: buildCriterias,
      categoryFilter: CategoryFilter.ExtendedFilter,
      selectableCriteria: selectableCriterias
    });
  }, [selectableCriterias, buildCriterias]);

  return (
    <>
      <div className={classes.moreFiltersButton}>
        <Button
          icon={open ? <KeyboardArrowLeftIcon /> : <KeyboardArrowRightIcon />}
          iconVariant="end"
          size="small"
          variant="ghost"
          onClick={controlFilterInterface}
        >
          <Typography variant="body1">
            {t(open ? labelShowFewerFilters : labelShowMoreFilters)}
          </Typography>
        </Button>
      </div>
      <div className={cx(classes.small, { [classes.extended]: open })}>
        <BasicFilter
          poller={
            <MemoizedPoller changeCriteria={changeCriteria} data={basicData} />
          }
          sections={
            <SectionWrapper
              changeCriteria={changeCriteria}
              data={basicData}
              searchData={searchData}
            />
          }
          state={
            <MemoizedCheckBox
              changeCriteria={changeCriteria}
              data={basicData}
              filterName={BasicCriteria.states}
              title={labelState}
            />
          }
          types={
            <MemoizedCheckBox
              changeCriteria={changeCriteria}
              data={basicData}
              filterName={BasicCriteria.resourceTypes}
              title={labelType}
            />
          }
        />

        {open && (
          <>
            <div className={classes.containerDivider}>
              <Divider
                flexItem
                className={classes.bridge}
                orientation="vertical"
                variant="middle"
              />
            </div>
            <ExtendedFilter
              changeCriteria={changeCriteria}
              data={extendedData}
            />
          </>
        )}
      </div>

      <Divider className={classes.footer} />

      {displayActions && actions}
    </>
  );
};

export default CriteriasNewInterface;
