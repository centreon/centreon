import { useMemoComponent } from '@centreon/ui';

import {
  BasicCriteria,
  MemoizedChildSectionWrapper,
  SectionType
} from '../../model';
import { findData } from '../../utils';
import InputGroup from '../InputGroup';

const MemoizedInputGroup = ({
  changeCriteria,
  basicData,
  sectionType
}: MemoizedChildSectionWrapper): JSX.Element => {
  const filterName =
    sectionType === SectionType.host
      ? BasicCriteria.hostGroups
      : BasicCriteria.serviceGroups;

  return useMemoComponent({
    Component: (
      <InputGroup
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={filterName}
        resourceType={sectionType}
      />
    ),
    memoProps: [
      findData({
        data: basicData,
        filterName
      })?.value
    ]
  });
};

export default MemoizedInputGroup;
