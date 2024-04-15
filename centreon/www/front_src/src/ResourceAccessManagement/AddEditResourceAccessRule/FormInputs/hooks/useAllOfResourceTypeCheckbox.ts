import { ChangeEvent } from 'react';

import { useAtom } from 'jotai';
import { useFormikContext } from 'formik';
import { equals } from 'ramda';

import { ResourceAccessRule, ResourceTypeEnum } from '../../../models';
import { selectedDatasetFiltersAtom } from '../../../atom';
import {
  labelAllHostGroups,
  labelAllHosts,
  labelAllServiceGroups
} from '../../../translatedLabels';

interface UseAllOfResourceTypeCheckboxState {
  checkboxLabel: string;
  checked: boolean;
  onChange: (event: ChangeEvent<HTMLInputElement>) => void;
}

const allOfResourceTypeLabels = {
  [ResourceTypeEnum.HostGroup]: labelAllHostGroups,
  [ResourceTypeEnum.Host]: labelAllHosts,
  [ResourceTypeEnum.ServiceGroup]: labelAllServiceGroups
};

export const useAllOfResourceTypeCheckbox = (
  datasetFilterIndex: number,
  datasetIndex: number,
  resourceType: ResourceTypeEnum
): UseAllOfResourceTypeCheckboxState => {
  const [selectedDatasetFilters, setSelectedDatasetFilters] = useAtom(
    selectedDatasetFiltersAtom
  );

  const { setFieldValue, setFieldTouched } =
    useFormikContext<ResourceAccessRule>();

  const checkboxLabel = allOfResourceTypeLabels[resourceType];

  const onChange = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldValue(
      `datasetFilters.${datasetFilterIndex}.${datasetIndex}.resources`,
      []
    );
    setFieldValue(
      `datasetFilters.${datasetFilterIndex}.${datasetIndex}.allOfResourceType`,
      event.target.checked
    );
    setFieldTouched(`datasetFilters.${datasetFilterIndex}`, true, false);
    setSelectedDatasetFilters(
      selectedDatasetFilters.map((datasets, indexFilter) => {
        if (equals(indexFilter, datasetFilterIndex)) {
          return selectedDatasetFilters[indexFilter].map((dataset, i) => {
            if (equals(i, datasetIndex)) {
              return {
                allOf: event.target.checked,
                ids: [],
                type: dataset.type
              };
            }

            return dataset;
          });
        }

        return datasets;
      })
    );
  };

  return {
    checkboxLabel,
    checked: selectedDatasetFilters[datasetFilterIndex][datasetIndex].allOf,
    onChange
  };
};
