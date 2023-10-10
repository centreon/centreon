import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Divider } from '@mui/material';

import { Criteria, CriteriaDisplayProps } from '../../Criterias/models';
import { SearchableFields } from '../../Criterias/searchQueryLanguage/models';
import { displayInformationFilterAtom } from '../basicFilter/atoms';
import { useStyles } from '../criterias.style';
import { ChangedCriteriaParams, ExtendedCriteria } from '../model';

import FilterSearch from './FilterSearch';
import MemoizedCheckBoxWrapper from './MemoizedCheckBoxWrapper';
import MemoizedInputGroup from './MemoizedInputGroup';
import MemoizedSelectInput from './MemoizedSelectInput';
import useExtendedFilter from './useExtendedFilter';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
}

const ExtendedFilter = ({ data, changeCriteria }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { resourceTypes, inputGroupsData, statusTypes } = useExtendedFilter({
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
      {resourceTypes?.map((item) => (
        <>
          <MemoizedSelectInput
            changeCriteria={changeCriteria}
            data={data}
            filterName={ExtendedCriteria.resourceTypes}
            key={item.name}
            resourceType={item.id}
          />
          <Divider className={classes.dividerInputs} />
        </>
      ))}

      {displayInformationFilter && (
        <FilterSearch
          field={SearchableFields.information}
          placeholder={t('Information') as string}
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
