// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';
import KeyboardArrowRightIcon from '@mui/icons-material/KeyboardArrowRight';
import { Divider, Typography } from '@mui/material';

import { Button } from '@centreon/ui/components';

import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { selectedVisualizationAtom } from '../../Actions/actionsAtoms';
import { Visualization } from '../../models';
import {
  labelGeneral,
  labelHost,
  labelService,
  labelState,
  labelStatusType,
  labelType
} from '../../translatedLabels';
import type { Criteria, CriteriaDisplayProps } from '../Criterias/models';
import { SearchableFields } from '../Criterias/searchQueryLanguage/models';
import { setCriteriaAndNewFilterDerivedAtom } from '../filterAtoms';
import MemoizedInputGroup from './basicFilter/sections/MemoizedInputGroup';
import MemoizedSelectInput from './basicFilter/sections/MemoizedSelectInput';
import MemoizedStatus from './basicFilter/sections/MemoizedStatus';
import { useStyles } from './criterias.style';
import FilterSearch from './extendedFilter/FilterSearch';
import MemoizedCheckBox from './MemoizedCheckBox';
import MemoizedPoller from './MemoizedPoller';
import {
  BasicCriteria,
  type BuildDataByCategoryFilter,
  CategoryFilter,
  type ChangedCriteriaParams,
  type Data,
  type DataByCategoryFilter,
  ExtendedCriteria,
  SectionType
} from './model';
import {
  informationLabel,
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
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);

  const setCriteriaAndNewFilter = useSetAtom(
    setCriteriaAndNewFilterDerivedAtom
  );

  const selectedVisualization = useAtomValue(selectedVisualizationAtom);
  const isHostStatusDeactivated = equals(
    selectedVisualization,
    Visualization.Host
  );

  const {
    newSelectableCriterias: buildCriterias,
    selectableCriterias,
    searchData
  } = data;

  const changeCriteria = ({
    updatedValue,
    filterName
  }: ChangedCriteriaParams): void => {
    setCriteriaAndNewFilter({ name: filterName, value: updatedValue });
  };

  const controlFilterInterface = (): void => {
    setOpen((currentOpen) => !currentOpen);
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
      builtCriteria,
      CriteriaType: criteriaType,
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
          onClick={controlFilterInterface}
          size="small"
          variant="ghost"
        >
          <Typography variant="body1">
            {t(open ? labelShowFewerFilters : labelShowMoreFilters)}
          </Typography>
        </Button>
      </div>

      <div className={classes.columns}>
        <div className={classes.column}>
          <Typography className={classes.columnTitle}>
            {t(labelHost)}
          </Typography>
          <MemoizedSelectInput
            changeCriteria={changeCriteria}
            data={basicData}
            filterName={BasicCriteria.parentNames}
            searchData={searchData}
            sectionType={SectionType.host}
          />
          <MemoizedStatus
            changeCriteria={changeCriteria}
            data={basicData}
            filterName={BasicCriteria.statues}
            isDeactivated={isHostStatusDeactivated}
            sectionType={SectionType.host}
          />
          <MemoizedInputGroup
            changeCriteria={changeCriteria}
            data={basicData}
            filterName={BasicCriteria.hostGroups}
            sectionType={SectionType.host}
          />
          {open && (
            <>
              <MemoizedInputGroup
                changeCriteria={changeCriteria}
                data={extendedData}
                filterName={ExtendedCriteria.hostCategories}
              />
              <MemoizedInputGroup
                changeCriteria={changeCriteria}
                data={extendedData}
                filterName={ExtendedCriteria.hostSeverities}
              />
            </>
          )}
        </div>

        <div className={classes.column}>
          <Typography className={classes.columnTitle}>
            {t(labelService)}
          </Typography>
          <MemoizedSelectInput
            changeCriteria={changeCriteria}
            data={basicData}
            filterName={BasicCriteria.names}
            searchData={searchData}
            sectionType={SectionType.service}
          />
          <MemoizedStatus
            changeCriteria={changeCriteria}
            data={basicData}
            filterName={BasicCriteria.statues}
            sectionType={SectionType.service}
          />
          <MemoizedInputGroup
            changeCriteria={changeCriteria}
            data={basicData}
            filterName={BasicCriteria.serviceGroups}
            sectionType={SectionType.service}
          />
          {open && (
            <>
              <MemoizedInputGroup
                changeCriteria={changeCriteria}
                data={extendedData}
                filterName={ExtendedCriteria.serviceCategories}
              />
              <MemoizedInputGroup
                changeCriteria={changeCriteria}
                data={extendedData}
                filterName={ExtendedCriteria.serviceSeverities}
              />
              <FilterSearch
                field={SearchableFields.information}
                placeholder={t(informationLabel) as string}
              />
            </>
          )}
        </div>

        <div className={classes.column}>
          <Typography className={classes.columnTitle}>
            {t(labelGeneral)}
          </Typography>
          <MemoizedPoller changeCriteria={changeCriteria} data={basicData} />
          <MemoizedCheckBox
            changeCriteria={changeCriteria}
            data={basicData}
            filterName={BasicCriteria.resourceTypes}
            title={labelType}
          />
          <MemoizedCheckBox
            changeCriteria={changeCriteria}
            data={basicData}
            filterName={BasicCriteria.states}
            title={labelState}
          />
          {open && (
            <MemoizedCheckBox
              changeCriteria={changeCriteria}
              data={extendedData}
              filterName={ExtendedCriteria.statusTypes}
              title={labelStatusType}
            />
          )}
        </div>
      </div>

      <Divider className={classes.footer} />

      {actions}
    </>
  );
};

export default CriteriasNewInterface;
