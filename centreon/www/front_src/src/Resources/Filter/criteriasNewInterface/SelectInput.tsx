import { useState } from 'react';

import { SelectField } from '@centreon/ui';

const SelectInput = (options): JSX.Element => {
  const [selectedOptionId, setSelectedOptionId] = useState(0);

  const changeSelectedOption = (event): void => {
    setSelectedOptionId(event.target.value);
  };

  return (
    <SelectField
      label="name"
      options={options}
      selectedOptionId={selectedOptionId}
      onChange={changeSelectedOption}
    />
  );
};

export default SelectInput;
