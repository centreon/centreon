import { useMemoComponent } from '@centreon/ui';

import { MemoizedChildSectionWrapper } from '../../model';
import { findData } from '../../utils';
import SelectInput from '../SelectInput';

const MemoizedSelectInput = ({
  sectionType,
  basicData,
  changeCriteria,
  filterName,
  searchData
}: MemoizedChildSectionWrapper): JSX.Element => {
  return useMemoComponent({
    Component: (
      <SelectInput
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={filterName}
        resourceType={sectionType}
        searchData={searchData}
      />
    ),
    memoProps: [
      findData({ data: basicData, filterName })?.value,
      searchData?.search
    ]
  });
};

export default MemoizedSelectInput;
