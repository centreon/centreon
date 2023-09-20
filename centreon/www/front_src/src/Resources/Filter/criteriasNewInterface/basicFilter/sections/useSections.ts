import { useEffect, useState } from 'react';

import { handleDataByCategoryFilter } from '../../utils';

const useSectionsData = ({ data, sectionType }) => {
  const [sectionData, setSectionData] = useState();

  useEffect(() => {
    if (!data) {
      return;
    }
    const filteredDataBySectionType = handleDataByCategoryFilter({
      data,
      fieldToUpdate: 'options',
      filter: sectionType
    });

    setSectionData(filteredDataBySectionType);
  }, [data]);

  return { sectionData };
};

export default useSectionsData;
