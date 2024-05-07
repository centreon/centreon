import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Variant } from '@mui/material/styles/createTypography';

import { CheckboxGroup, SelectEntry } from '@centreon/ui';

import { Criteria, CriteriaDisplayProps } from '../../../Criterias/models';
import {
  ChangedCriteriaParams,
  DeactivateProps,
  SectionType,
  SelectedResourceType
} from '../../model';
import useInputData from '../../useInputsData';
import { findData } from '../../utils';
import { selectedStatusByResourceTypeAtom } from '../atoms';
import useSectionsData from '../sections/useSections';

import { useStyles } from './checkBox.style';
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

  const { values } = useCheckBox({
    changeCriteria,
    data,
    filterName,
    resourceType,
    selectedStatusByResourceType,
    setSelectedStatusByResourceType
  });

  if (!dataByFilterName || isDeactivated) {
    return null;
  }

  const transformData = (
    input: Array<SelectEntry>
  ): Array<string> | undefined => {
    return input?.map((item) => item?.name);
  };

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

  const getTranslated = (keys: Array<SelectEntry>): Array<SelectEntry> => {
    return keys.map((entry) => ({
      id: entry.id,
      name: t(entry.name)
    }));
  };

  const translatedOptions = getTranslated(dataByFilterName?.options);
  const translatedValues = getTranslated(values);

  const handleSelectedStatus = (value): void => {
    const arrayToFilter = selectedStatusByResourceType
      ? [...selectedStatusByResourceType, value]
      : [value];

    const result = [
      ...new Map(
        arrayToFilter.map((element) => {
          const key = `${element?.id}${element.resourceType}`;

          return [key, element];
        })
      ).values()
    ];

    setSelectedStatusByResourceType(result as Array<SelectedResourceType>);
    changeFilter(result as Array<SelectedResourceType>);
  };

  const handleChangeStatus = (event): void => {
    const originalValue = translatedOptions.find(({ name }) =>
      equals(name, event.target.id)
    );
    const item = findData({
      data: dataByFilterName?.options,
      filterName: originalValue?.id,
      findBy: 'id'
    });

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
