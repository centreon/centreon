/* eslint-disable react-hooks/rules-of-hooks */
import React, { useState } from 'react';

import SelectField from '.';

export default { title: 'InputField/Select' };

const options = [
  { id: 0, name: 'First Entity' },
  { id: 1, name: 'Second Entity' },
  { id: 2, name: 'Third Entity' },
];

export const withThreeOptions = (): JSX.Element => {
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

export const withError = (): JSX.Element => (
  <SelectField
    label="name"
    options={[{ id: 0, name: 'Selected' }]}
    selectedOptionId={0}
    onChange={(): void => undefined}
    error="Something went wrong"
  />
);

export const compact = (): JSX.Element => (
  <SelectField
    options={[{ id: 0, name: 'Tiny' }]}
    selectedOptionId={0}
    onChange={(): void => undefined}
    compact
  />
);

export const openWithColors = (): JSX.Element => (
  <SelectField
    options={[
      { id: 0, name: 'Red', color: 'red' },
      { id: 0, name: 'Yellow', color: 'yellow' },
    ]}
    selectedOptionId={0}
    onChange={(): void => undefined}
    open
  />
);
