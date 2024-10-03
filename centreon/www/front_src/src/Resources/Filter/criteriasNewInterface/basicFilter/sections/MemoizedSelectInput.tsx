import { useMemoComponent } from '@centreon/ui';

import {
  DeactivateProps,
  MemoizedChildSectionWrapper,
  SectionType
} from '../../model';
import { findData } from '../../utils';
import SelectInput from '../SelectInput';

const MemoizedSelectInput = ({
  sectionType,
  data,
  changeCriteria,
  filterName,
  searchData,
  isDeactivated
}: MemoizedChildSectionWrapper & DeactivateProps): JSX.Element => {
  return useMemoComponent({
    Component: (
      <SelectInput
        changeCriteria={changeCriteria}
        data={data}
        filterName={filterName}
        isDeactivated={isDeactivated}
        resourceType={sectionType as SectionType}
      />
    ),
    memoProps: [
      ...findData({ data, filterName }),
      searchData?.search,
      isDeactivated
    ]
  });
};

export default MemoizedSelectInput;
