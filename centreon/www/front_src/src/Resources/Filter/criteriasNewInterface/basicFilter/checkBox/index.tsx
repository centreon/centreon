// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { SelectEntry } from '@centreon/ui';

import { useAtom } from 'jotai';
import { equals } from 'ramda';

import { Criteria, CriteriaDisplayProps } from '../../../Criterias/models';
import {
  BasicCriteria,
  ChangedCriteriaParams,
  DeactivateProps,
  ExtendedCriteria,
  SectionType,
  SelectedResourceType
} from '../../model';
import useInputData from '../../useInputsData';
import { selectedStatusByResourceTypeAtom } from '../atoms';
import useSectionsData from '../sections/useSections';
import StatusChipGroup from './StatusChipGroup';
import useSynchronizeSearchBarWithCheckBoxInterface from './useSynchronizeSearchBarWithCheckBoxInterface';

interface Props {
  changeCriteria: (data: ChangedCriteriaParams) => void;
  data: Array<Criteria & CriteriaDisplayProps>;
  filterName: BasicCriteria | ExtendedCriteria;
  resourceType: SectionType;
}

const CheckBoxSection = ({
  data,
  filterName,
  changeCriteria,
  resourceType,
  isDeactivated
}: Props & DeactivateProps): JSX.Element | null => {
  const [selectedStatusByResourceType, setSelectedStatusByResourceType] =
    useAtom(selectedStatusByResourceTypeAtom);

  const { sectionData } = useSectionsData({ data, sectionType: resourceType });

  const { dataByFilterName } = useInputData({
    data: sectionData,
    filterName
  });

  useSynchronizeSearchBarWithCheckBoxInterface({
    data,
    filterName,
    resourceType,
    setSelectedStatusByResourceType
  });

  if (!dataByFilterName || isDeactivated) {
    return null;
  }

  const options = dataByFilterName.options as Array<SelectEntry>;

  const changeFilter = (selectedStatus: Array<SelectedResourceType>): void => {
    const checkedData = selectedStatus?.filter((item) => item?.checked);
    const updatedValue = checkedData?.map((element) => ({
      id: element?.id,
      name: element?.name
    }));
    changeCriteria({
      filterName,
      updatedValue
    });
  };

  const handleSelectedStatus = (newStatus): void => {
    if (selectedStatusByResourceType) {
      const oldStatus = selectedStatusByResourceType.find(
        ({ id, resourceType: type }) =>
          equals(id, newStatus.id) && equals(type, newStatus.resourceType)
      );

      const newArrayStatus = oldStatus
        ? selectedStatusByResourceType.filter(
            (item) =>
              !equals(
                `${item.id}${item.resourceType}`,
                `${oldStatus.id}${oldStatus.resourceType}`
              )
          )
        : selectedStatusByResourceType;

      const result = [
        ...newArrayStatus,
        newStatus
      ] as Array<SelectedResourceType>;

      setSelectedStatusByResourceType(result);
      changeFilter(result);

      return;
    }

    const result = [newStatus] as Array<SelectedResourceType>;

    setSelectedStatusByResourceType(result);
    changeFilter(result);
  };

  const selectedIds = (selectedStatusByResourceType ?? [])
    .filter((entry) => entry.checked && entry.resourceType === resourceType)
    .map((entry) => entry.id);

  const toggleStatus = (option: SelectEntry, checked: boolean): void => {
    handleSelectedStatus({ ...option, checked, resourceType });
  };

  return (
    <StatusChipGroup
      dataTestId={`${filterName}-${resourceType}`}
      onToggle={toggleStatus}
      options={options}
      selectedIds={selectedIds}
    />
  );
};

export default CheckBoxSection;
