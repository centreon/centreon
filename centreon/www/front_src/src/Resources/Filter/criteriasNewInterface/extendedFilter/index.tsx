import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Divider } from '@mui/material';

import { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import { SearchableFields } from '../../Criterias/searchQueryLanguage/models';
import { displayInformationFilterAtom } from '../basicFilter/atoms';
import { useStyles } from '../criterias.style';
import { ChangedCriteriaParams } from '../model';
import { informationLabel } from '../translatedLabels';

import FilterSearch from './FilterSearch';
import MemoizedCheckBoxWrapper from './MemoizedCheckBoxWrapper';
import MemoizedInputGroup from './MemoizedInputGroup';
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
            filterName={item.name}
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
        <MemoizedCheckBoxWrapper
          changeCriteria={changeCriteria}
          data={statusTypes}
        />
      )}
      <Divider className={classes.dividerInputs} />
    </div>
  );
};

export default ExtendedFilter;
