import { useAtomValue } from 'jotai';

import { useMemoComponent } from '@centreon/ui';

import {
  DeactivateProps,
  MemoizedChildSectionWrapper,
  SectionType
} from '../../model';
import { findData } from '../../utils';
import { selectedStatusByResourceTypeAtom } from '../atoms';
import CheckBoxSection from '../checkBox';

const MemoizedStatus = ({
  changeCriteria,
  data,
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
        data={data}
        filterName={filterName}
        isDeactivated={isDeactivated}
        resourceType={sectionType as SectionType}
      />
    ),
    memoProps: [
      selectedStatusByResourceType,
      ...findData({
        data,
        filterName
      }),
      isDeactivated
    ]
  });
};
export default MemoizedStatus;
