import { useMemoComponent } from '@centreon/ui';

import { MemoizedChildSectionWrapper } from '../../model';
import { findData } from '../../utils';
import InputGroup from '../InputGroup';

const MemoizedInputGroup = ({
  changeCriteria,
  basicData,
  sectionType,
  filterName
}: MemoizedChildSectionWrapper): JSX.Element => {
  return useMemoComponent({
    Component: (
      <InputGroup
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={filterName}
        resourceType={sectionType}
      />
    ),
    memoProps: [
      findData({
        data: basicData,
        filterName
      })?.value
    ]
  });
};

export default MemoizedInputGroup;
