import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Variant } from '@mui/material/styles/createTypography';

import { CheckboxGroup, SelectEntry } from '@centreon/ui';

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

import { useStyles } from './checkBox.style';
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
  const { classes } = useStyles();
  const { t } = useTranslation();

  const labelProps = {
    classes: { root: classes.label },
    variant: 'body2' as Variant
  };

  const formGroupProps = { classes: { root: classes.container } };

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

  const transformData = (
    input: Array<SelectEntry>
  ): Array<string> | undefined => {
    return input?.map((item) => item?.name);
  };

  const getTranslated = (keys: Array<SelectEntry>): Array<SelectEntry> => {
    return keys.map((entry) => ({
      id: entry.id,
      name: t(entry.name)
    }));
  };

  const translatedOptions = getTranslated(options);

  const translatedValues = getTranslated(
    selectedStatusByResourceType?.filter(
      (e) => e.checked && e.resourceType === resourceType
    ) ?? []
  );

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

  const handleChangeStatus = (event): void => {
    const originalValue = translatedOptions.find(({ name }) =>
      equals(name, event.target.id)
    ) as SelectEntry;

    const item = options.find(({ id }) => equals(id, originalValue.id));

    if (event.target.checked) {
      const currentValue = { ...item, checked: true, resourceType };
      handleSelectedStatus(currentValue);

      return;
    }

    const currentValue = { ...item, checked: false, resourceType };

    handleSelectedStatus(currentValue);
  };

  return (
    <CheckboxGroup
      className={classes.checkbox}
      dataTestId={`${filterName}-${resourceType}`}
      direction="horizontal"
      formGroupProps={formGroupProps}
      labelProps={labelProps}
      options={transformData(translatedOptions) || []}
      values={transformData(translatedValues) || []}
      onChange={handleChangeStatus}
    />
  );
};

export default CheckBoxSection;
