import { useMemoComponent } from '@centreon/ui';

import { findData } from '../utils';
import InputGroup from '../basicFilter/InputGroup';

const MemoizedInputGroup = ({
  data,
  changeCriteria,
  filterName
}): JSX.Element => {
  return useMemoComponent({
    Component: (
      <InputGroup
        changeCriteria={changeCriteria}
        data={data}
        filterName={filterName}
      />
    ),
    memoProps: [findData({ data, filterName })?.value]
  });
};
export default MemoizedInputGroup;
