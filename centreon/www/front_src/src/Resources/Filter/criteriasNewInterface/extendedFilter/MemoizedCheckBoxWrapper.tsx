import { useMemoComponent } from '@centreon/ui';

import { CheckBoxWrapper } from '../CheckBoxWrapper';
import { ExtendedCriteria } from '../model';
import { findData } from '../utils';

const MemoizedCheckBoxWrapper = ({ changeCriteria, data }): JSX.Element => {
  return useMemoComponent({
    Component: (
      <CheckBoxWrapper
        changeCriteria={changeCriteria}
        data={data}
        filterName={ExtendedCriteria.statusTypes}
        title="Status type"
      />
    ),

    memoProps: [
      findData({
        data,
        filterName: ExtendedCriteria.statusTypes
      })?.value
    ]
  });
};

export default MemoizedCheckBoxWrapper;
