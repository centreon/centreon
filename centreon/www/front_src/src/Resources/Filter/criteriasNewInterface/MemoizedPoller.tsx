import { useMemoComponent } from '@centreon/ui';

import InputGroup from './basicFilter/InputGroup';
import { BasicCriteria, MemoizedChild } from './model';
import { findData } from './utils';

const MemoizedPoller = ({
  basicData,
  changeCriteria
}: MemoizedChild): JSX.Element => {
  return useMemoComponent({
    Component: (
      <InputGroup
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={BasicCriteria.monitoringServers}
        label="Poller"
      />
    ),
    memoProps: [
      findData({
        data: basicData,
        filterName: BasicCriteria.monitoringServers
      })?.value
    ]
  });
};
export default MemoizedPoller;
