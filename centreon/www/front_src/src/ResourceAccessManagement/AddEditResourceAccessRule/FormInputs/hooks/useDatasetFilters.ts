import { useMemo } from 'react';

import { useAtom } from 'jotai';
import { FormikValues, useFormikContext } from 'formik';
import { equals } from 'ramda';

import { Dataset, ResourceTypeEnum } from '../../../models';
import { selectedDatasetFiltersAtom } from '../../../atom';

type UseDatasetFiltersState = {
  addDatasetFilter: () => void;
  datasetFilters: Array<Array<Dataset>>;
  deleteDatasetFilter: (index: number) => () => void;
};

const useDatasetFilters = (): UseDatasetFiltersState => {
  const [selectedDatasetFilters, setSelectedDatasetFilters] = useAtom(
    selectedDatasetFiltersAtom
  );
  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const datasetFilters = useMemo<Array<Array<Dataset>> | undefined>(
    () => values.datasetFilters,
    [values.datasetFilters]
  );

  const addDatasetFilter = (): void => {
    setFieldValue('datasetFilters', [
      ...(datasetFilters || []),
      [
        {
          allOfResourceType: false,
          resourceType: '',
          resources: []
        }
      ]
    ]);
    setSelectedDatasetFilters([
      ...selectedDatasetFilters,
      [
        {
          allOf: false,
          ids: [],
          type: ResourceTypeEnum.Empty
        }
      ]
    ]);
  };

  const deleteDatasetFilter = (index: number) => (): void => {
    setFieldValue(
      'datasetFilters',
      (datasetFilters || []).filter((_, i) => !equals(i, index))
    );
    setSelectedDatasetFilters(
      selectedDatasetFilters.filter((_, i) => !equals(i, index))
    );
  };

  return {
    addDatasetFilter,
    datasetFilters: datasetFilters || [],
    deleteDatasetFilter
  };
};

export default useDatasetFilters;
