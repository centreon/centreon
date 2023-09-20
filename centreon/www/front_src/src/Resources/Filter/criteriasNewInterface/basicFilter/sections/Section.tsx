import { useSetAtom } from 'jotai';

import { setCriteriaAndNewFilterDerivedAtom } from '../../../filterAtoms';

import useSectionsData from './useSections';

const Section = ({
  renderSelectInput,
  renderStatus,
  renderInputGroup,
  data,
  sectionType
}) => {
  const { sectionData } = useSectionsData({ data, sectionType });

  return (
    <>
      {renderSelectInput?.({ sectionData })}
      {renderStatus?.({ sectionData })}
      {renderInputGroup?.({ sectionData })}
    </>
  );
};

export default Section;
