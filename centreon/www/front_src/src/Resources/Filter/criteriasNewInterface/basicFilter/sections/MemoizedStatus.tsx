import { useAtomValue } from 'jotai';

import { useMemoComponent } from '@centreon/ui';

import { MemoizedChildSectionWrapper } from '../../model';
import { selectedStatusByResourceTypeAtom } from '../atoms';
import { findData } from '../../utils';
import CheckBoxSection from '../checkBox';

const MemoizedStatus = ({
  changeCriteria,
  basicData,
  sectionType,
  filterName
}: MemoizedChildSectionWrapper): JSX.Element => {
  const selectedStatusByResourceType = useAtomValue(
    selectedStatusByResourceTypeAtom
  );

  return useMemoComponent({
    Component: (
      <CheckBoxSection
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={filterName}
        resourceType={sectionType}
      />
    ),
    memoProps: [
      selectedStatusByResourceType,
      findData({
        data: basicData,
        filterName
      })?.value
    ]
  });
};
export default MemoizedStatus;
