import { useState } from 'react';

import { FluidTypography, CheckboxGroup } from '@centreon/ui';

interface Props {
  options: Array<string>;
  title?: string;
}

export const CheckBoxWrapper = ({ title, options }: Props): JSX.Element => {
  const [values, setValues] = useState([]);

  const handleChangeCheckBox = (value): void => {
    setValues(value);
  };

  return (
    <>
      {title && <FluidTypography text={title} />}
      <CheckboxGroup
        direction="horizontal"
        options={options}
        values={values}
        onChange={handleChangeCheckBox}
      />
    </>
  );
};

// export default CheckBoxWrapper;
