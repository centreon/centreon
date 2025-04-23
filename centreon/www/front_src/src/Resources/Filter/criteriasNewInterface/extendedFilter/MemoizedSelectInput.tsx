import { useMemoComponent } from '@centreon/ui';

import SelectInput from '../basicFilter/SelectInput';
import { findData } from '../utils';

const MemoizedSelectInput = ({
  data,
  changeCriteria,
  filterName,
  resourceType
}): JSX.Element => {
  return useMemoComponent({
    Component: (
      <SelectInput
        changeCriteria={changeCriteria}
        data={data}
        filterName={filterName}
        resourceType={resourceType}
      />
    ),
    memoProps: findData({ data, filterName })
  });
};

export default MemoizedSelectInput;
