import { useAtom } from 'jotai';
import { isEmpty, isNil } from 'ramda';

import { CheckboxGroup, SelectEntry } from '@centreon/ui';

import { Criteria, CriteriaDisplayProps } from '../../../Criterias/models';
import {
  ChangedCriteriaParams,
  SectionType,
  SelectedResourceType
} from '../../model';
import useInputData from '../../useInputsData';
import { findData, removeDuplicateFromObjectArray } from '../../utils';
import { selectedStatusByResourceTypeAtom } from '../atoms';
import useSectionsData from '../sections/useSections';

import useCheckBox from './useCheckBox';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: string;
  resourceType: SectionType;
}

const CheckBoxSection = ({
  data,
  filterName,
  changeCriteria,
  resourceType
}: Props): JSX.Element => {
  const [selectedStatusByResourceType, setSelectedStatusByResourceType] =
    useAtom(selectedStatusByResourceTypeAtom);

  const { sectionData } = useSectionsData({ data, sectionType: resourceType });

  const { dataByFilterName } = useInputData({
    data: sectionData,
    filterName
  });

  const { values } = useCheckBox({
    changeCriteria,
    data,
    filterName,
    resourceType,
    selectedStatusByResourceType,
    setSelectedStatusByResourceType
  });

  const transformData = (
    input: Array<SelectEntry>
  ): Array<string> | undefined => {
    return input?.map((item) => item?.name);
  };

  const handleChangeStatus = (event): void => {
    const item = findData({
      data: dataByFilterName?.options,
      filterName: event.target.id
    });

    if (event.target.checked) {
      const currentValue = { ...item, checked: true, resourceType };
      const result = removeDuplicateFromObjectArray({
        array: selectedStatusByResourceType
          ? [...selectedStatusByResourceType, currentValue]
          : [currentValue],
        byFields: ['id', 'resourceType']
      });
      setSelectedStatusByResourceType(result as Array<SelectedResourceType>);

      return;
    }

    const currentItem = { ...item, checked: false, resourceType };

    const result = removeDuplicateFromObjectArray({
      array: selectedStatusByResourceType
        ? [...selectedStatusByResourceType, currentItem]
        : [currentItem],
      byFields: ['id', 'resourceType']
    });
    setSelectedStatusByResourceType(result as Array<SelectedResourceType>);
  };

  return (
    <CheckboxGroup
      direction="horizontal"
      options={transformData(dataByFilterName?.options) ?? []}
      values={transformData(values) || []}
      onChange={(event) => handleChangeStatus(event)}
    />
  );
};

export default CheckBoxSection;
