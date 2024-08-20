import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Divider } from '@mui/material';

import { labelStatusType } from '../../../translatedLabels';
import { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import { SearchableFields } from '../../Criterias/searchQueryLanguage/models';
import MemoizedCheckBox from '../MemoizedCheckBox';
import { displayInformationFilterAtom } from '../basicFilter/atoms';
import MemoizedInputGroup from '../basicFilter/sections/MemoizedInputGroup';
import { useStyles } from '../criterias.style';
import { ChangedCriteriaParams, ExtendedCriteria } from '../model';
import { informationLabel } from '../translatedLabels';

import FilterSearch from './FilterSearch';
import useExtendedFilter from './useExtendedFilter';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
}

const ExtendedFilter = ({ data, changeCriteria }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { inputGroupsData, statusTypes } = useExtendedFilter({
    data
  });

  const displayInformationFilter = useAtomValue(displayInformationFilterAtom);

  return (
    <div className={classes.containerFilter}>
      {inputGroupsData?.map((item) => (
        <>
          <MemoizedInputGroup
            changeCriteria={changeCriteria}
            data={data}
            filterName={item.name as ExtendedCriteria}
            key={item.name}
          />
          <Divider className={classes.dividerInputs} />
        </>
      ))}

      {displayInformationFilter && (
        <FilterSearch
          field={SearchableFields.information}
          placeholder={t(informationLabel) as string}
        />
      )}
      <Divider className={classes.dividerInputs} />

      {displayInformationFilter && (
        <MemoizedCheckBox
          changeCriteria={changeCriteria}
          data={statusTypes as Array<Criteria & CriteriaDisplayProps>}
          filterName={ExtendedCriteria.statusTypes}
          title={labelStatusType}
        />
      )}
      <Divider className={classes.dividerInputs} />
    </div>
  );
};

export default ExtendedFilter;
