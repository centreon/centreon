import { useMemoComponent } from '@centreon/ui';

import { BasicCriteria, MemoizedChildSectionWrapper } from '../../model';
import { findData } from '../../utils';
import SelectInput from '../SelectInput';

const MemoizedSelectInput = ({
  sectionType,
  basicData,
  changeCriteria
}: MemoizedChildSectionWrapper): JSX.Element => {
  return useMemoComponent({
    Component: (
      <SelectInput
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={BasicCriteria.resourceTypes}
        resourceType={sectionType}
      />
    ),
    memoProps: [
      findData({ data: basicData, filterName: BasicCriteria.resourceTypes })
        ?.value,
      findData({ data: basicData, filterName: BasicCriteria.resourceTypes })
        ?.searchData?.values
    ]
  });
};

export default MemoizedSelectInput;
