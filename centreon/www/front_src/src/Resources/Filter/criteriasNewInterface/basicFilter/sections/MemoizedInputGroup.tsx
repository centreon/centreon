import { useMemoComponent } from '@centreon/ui';

import { MemoizedChildSectionWrapper } from '../../model';
import { findData } from '../../utils';
import InputGroup from '../InputGroup';

const MemoizedInputGroup = ({
  changeCriteria,
  data,
  sectionType,
  filterName
}: MemoizedChildSectionWrapper): JSX.Element => {
  return useMemoComponent({
    Component: (
      <InputGroup
        changeCriteria={changeCriteria}
        data={data}
        filterName={filterName}
        resourceType={sectionType}
      />
    ),
    memoProps: findData({
      data,
      filterName
    })
  });
};

export default MemoizedInputGroup;
