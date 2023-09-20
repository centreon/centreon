import { MultiAutocompleteField } from '@centreon/ui';

import useInputData from '../useInputsData';
import { BasicCriteria } from '../model';

const SelectInput = ({
  data,
  filterName,
  sectionType,
  changeCriteria
}): JSX.Element => {
  const { options, values, target } = useInputData({ data, filterName });

  const handleChange = (updatedValue) => {
    const updatedValues =
      updatedValue.length > 0
        ? [...updatedValue, ...values]
        : values?.filter((item) => item?.id !== sectionType);

    changeCriteria({
      filterName: BasicCriteria.resourceTypes,
      updatedValue: updatedValues
    });
  };

  return (
    <div>
      {options && (
        <MultiAutocompleteField
          label={sectionType}
          options={options.filter(({ id }) => id === sectionType)}
          placeholder={target?.label}
          value={values && values.filter(({ id }) => id === sectionType)}
          onChange={(event, updatedValue): void => handleChange(updatedValue)}
        />
      )}
    </div>
  );
};

export default SelectInput;
