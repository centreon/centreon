import { useMemo } from 'react';

import { Criteria, CriteriaDisplayProps } from '../../../Criterias/models';
import { SectionType } from '../../model';
import { handleDataByCategoryFilter } from '../../utils';

interface Parameters {
  data: Array<Criteria & CriteriaDisplayProps>;
  sectionType?: SectionType;
}

interface UseSectionData {
  sectionData: Array<Criteria & CriteriaDisplayProps>;
}

const useSectionsData = ({ data, sectionType }: Parameters): UseSectionData => {
  const sectionData = useMemo(() => {
    if (!data) {
      return [];
    }
    if (!sectionType) {
      return data;
    }

    return handleDataByCategoryFilter({
      data,
      fieldToUpdate: 'options',
      filter: sectionType
    });
  }, [data, sectionType]);

  return { sectionData };
};

export default useSectionsData;
