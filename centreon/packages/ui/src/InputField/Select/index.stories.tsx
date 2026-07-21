/* eslint-disable react-hooks/rules-of-hooks */
import { useState } from 'react';

import SelectField from '.';

export default { title: 'InputField/Select' };

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' }
];

export const withThreeOptions = (): JSX.Element => {
  const [selectedOptionId, setSelectedOptionId] = useState(0);

  const changeSelectedOption = (event): void => {
    setSelectedOptionId(event.target.value);
  };

  return (
    <SelectField
      label="name"
      onChange={changeSelectedOption}
      options={options}
      selectedOptionId={selectedOptionId}
    />
  );
};

export const withError = (): JSX.Element => (
  <SelectField
    error="Something went wrong"
    label="name"
    onChange={(): void => undefined}
    options={[{ id: 0, name: 'Selected' }]}
    selectedOptionId={0}
  />
);

export const compact = (): JSX.Element => (
  <SelectField
    compact
    onChange={(): void => undefined}
    options={[{ id: 0, name: 'Tiny' }]}
    selectedOptionId={0}
  />
);

export const openWithColors = (): JSX.Element => (
  <SelectField
    onChange={(): void => undefined}
    open
    options={[
      { color: 'red', id: 0, name: 'Red' },
      { color: 'yellow', id: 1, name: 'Yellow' }
    ]}
    selectedOptionId={0}
  />
);

export const openWithHeader = (): JSX.Element => (
  <SelectField
    onChange={(): void => undefined}
    open
    options={[
      {
        id: 0,
        name: 'Header',
        type: 'header'
      },
      { id: 1, name: 'Item' }
    ]}
    selectedOptionId={0}
  />
);
