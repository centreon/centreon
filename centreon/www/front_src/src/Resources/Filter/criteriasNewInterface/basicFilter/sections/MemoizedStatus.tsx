import { useAtomValue } from 'jotai';

import { useMemoComponent } from '@centreon/ui';

import { DeactivateProps, MemoizedChildSectionWrapper } from '../../model';
import { selectedStatusByResourceTypeAtom } from '../atoms';
import { findData } from '../../utils';
import CheckBoxSection from '../checkBox';

const MemoizedStatus = ({
  changeCriteria,
  basicData,
  sectionType,
  filterName,
  isDeactivated
}: MemoizedChildSectionWrapper & DeactivateProps): JSX.Element => {
  const selectedStatusByResourceType = useAtomValue(
    selectedStatusByResourceTypeAtom
  );

  return useMemoComponent({
    Component: (
      <CheckBoxSection
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={filterName}
        isDeactivated={isDeactivated}
        resourceType={sectionType}
      />
    ),
    memoProps: [
      selectedStatusByResourceType,
      findData({
        data: basicData,
        filterName
      })?.value,
      isDeactivated
    ]
  });
};
export default MemoizedStatus;
