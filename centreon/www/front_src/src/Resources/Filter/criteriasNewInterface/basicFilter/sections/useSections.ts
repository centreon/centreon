import { useMemo } from 'react';

import { handleDataByCategoryFilter } from '../../utils';

const useSectionsData = ({ data, sectionType }) => {
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
