import { useMemoComponent } from '@centreon/ui';

import { DeactivateProps, MemoizedChildSectionWrapper } from '../../model';
import { findData } from '../../utils';
import SelectInput from '../SelectInput';

const MemoizedSelectInput = ({
  sectionType,
  basicData,
  changeCriteria,
  filterName,
  searchData,
  isDeactivated
}: MemoizedChildSectionWrapper & DeactivateProps): JSX.Element => {
  return useMemoComponent({
    Component: (
      <SelectInput
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={filterName}
        isDeactivated={isDeactivated}
        resourceType={sectionType}
      />
    ),
    memoProps: [
      findData({ data: basicData, filterName })?.value,
      searchData?.search,
      isDeactivated
    ]
  });
};

export default MemoizedSelectInput;
