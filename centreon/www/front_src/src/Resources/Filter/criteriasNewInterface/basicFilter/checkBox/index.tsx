import { useAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { equals, propEq, reject } from 'ramda';

import { Variant } from '@mui/material/styles/createTypography';

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
  resourceType
}: Props): JSX.Element | null => {
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

  if (!dataByFilterName) {
    return null;
  }

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

  const translatedOptions = getTranslated(dataByFilterName?.options);
  const translatedValues = getTranslated(values);

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

    const result = reject(
      propEq('id', item?.id),
      removeDuplicateFromObjectArray({
        array: selectedStatusByResourceType
          ? [...selectedStatusByResourceType, currentItem]
          : [currentItem],
        byFields: ['id', 'resourceType']
      })
    );
    setSelectedStatusByResourceType(result as Array<SelectedResourceType>);
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
