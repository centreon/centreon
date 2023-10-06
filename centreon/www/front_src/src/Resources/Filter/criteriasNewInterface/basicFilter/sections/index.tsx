import { useAtomValue } from 'jotai';

import { Divider } from '@mui/material';

import { useMemoComponent } from '@centreon/ui';

import { BasicCriteria, SectionType } from '../../model';
import { findData } from '../../utils';
import CheckBoxSection from '../CheckBox';
import InputGroup from '../InputGroup';
import SelectInput from '../SelectInput';
import { selectedStatusByResourceTypeAtom } from '../atoms';

import Section from './Section';

const SectionWrapper = ({ basicData, changeCriteria }): JSX.Element => {
  const sectionsType = Object.values(SectionType);

  return (
    <div>
      {sectionsType?.map((sectionType) => (
        <>
          <Section
            inputGroup={
              <MemoizedInputGroup
                basicData={basicData}
                changeCriteria={changeCriteria}
                sectionType={sectionType}
              />
            }
            selectInput={
              <MemoizedSelectInput
                basicData={basicData}
                changeCriteria={changeCriteria}
                sectionType={sectionType}
              />
            }
            status={
              <MemoizedStatus
                basicData={basicData}
                changeCriteria={changeCriteria}
                sectionType={sectionType}
              />
            }
          />
          <Divider sx={{ marginBottom: 5 }} />
        </>
      ))}
    </div>
  );
};

const MemoizedSelectInput = ({
  sectionType,
  basicData,
  changeCriteria
}): JSX.Element => {
  return useMemoComponent({
    Component: (
      <SelectInput
        changeCriteria={changeCriteria}
        data={basicData}
        filterName={BasicCriteria.resourceTypes}
        resourceType={sectionType}
      />
    ),
    memoProps: [
      findData({ data: basicData, target: BasicCriteria.resourceTypes })?.value,
      findData({ data: basicData, target: BasicCriteria.resourceTypes })
        ?.searchData?.values
    ]
  });
};

const MemoizedStatus = ({
  changeCriteria,
  basicData,
  sectionType
}): JSX.Element => {
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
        target: BasicCriteria.statues
      })?.value
    ]
  });
};

const MemoizedInputGroup = ({
  changeCriteria,
  basicData,
  sectionType
}): JSX.Element => {
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
        target: filterName
      })?.value
    ]
  });
};

export default SectionWrapper;
