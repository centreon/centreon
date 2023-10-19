import { useMemoComponent } from '@centreon/ui';

import { BasicCriteria, MemoizedChildSectionWrapper } from '../../model';
import { findData } from '../../utils';
import SelectInput from '../SelectInput';

const MemoizedSelectInput = ({
  sectionType,
  basicData,
  changeCriteria,
  searchData
}: MemoizedChildSectionWrapper): JSX.Element => {
  return useMemoComponent({
    Component: (
      <SelectInput
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={BasicCriteria.resourceTypes}
        resourceType={sectionType}
        searchData={searchData}
      />
    ),
    memoProps: [
      findData({ data: basicData, filterName: BasicCriteria.resourceTypes })
        ?.value,
      findData({ data: basicData, filterName: BasicCriteria.resourceTypes })
        ?.search_data?.values,
      searchData?.search
    ]
  });
};

export default MemoizedSelectInput;
