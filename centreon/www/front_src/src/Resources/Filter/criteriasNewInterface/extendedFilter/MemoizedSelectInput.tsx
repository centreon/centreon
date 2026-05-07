import { useMemoComponent } from '@centreon/ui';

import { ReactElement } from 'react';

import SelectInput from '../basicFilter/SelectInput';
import { findData } from '../utils';

interface MemoizedSelectInputProps {
  changeCriteria: (...args: Array<unknown>) => void;
  data: Record<string, unknown>;
  filterName: string;
  resourceType?: string;
}

const MemoizedSelectInput = ({
  data,
  changeCriteria,
  filterName,
  resourceType
}: MemoizedSelectInputProps): ReactElement => {
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
