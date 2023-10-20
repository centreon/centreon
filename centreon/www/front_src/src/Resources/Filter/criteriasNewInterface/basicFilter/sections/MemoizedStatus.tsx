import { useAtomValue } from 'jotai';

import { useMemoComponent } from '@centreon/ui';

import { BasicCriteria, MemoizedChildSectionWrapper } from '../../model';
import { selectedStatusByResourceTypeAtom } from '../atoms';
import { findData } from '../../utils';
import CheckBoxSection from '../checkBox';

const MemoizedStatus = ({
  changeCriteria,
  basicData,
  sectionType
}: MemoizedChildSectionWrapper): JSX.Element => {
  const selectedStatusByResourceType = useAtomValue(
    selectedStatusByResourceTypeAtom
  );

  return useMemoComponent({
    Component: (
      <CheckBoxSection
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={BasicCriteria.statues}
        resourceType={sectionType}
      />
    ),
    memoProps: [
      selectedStatusByResourceType,
      findData({
        data: basicData,
        filterName: BasicCriteria.statues
      })?.value
    ]
  });
};
export default MemoizedStatus;
