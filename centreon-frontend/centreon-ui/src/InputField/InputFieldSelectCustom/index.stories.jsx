import React from 'react';

import InputFieldSelect from '.';

export default { title: 'InputField/Select' };

export const normal = () => (
  <InputFieldSelect
    options={[
      { id: '1', name: '24x7', alias: 'Always' },
      { id: '2', name: 'none', alias: 'Never' },
      { id: '3', name: 'nonworkhours', alias: 'Non-Work Hours' },
      { id: '4', name: 'workhours', alias: 'Work hours' },
    ]}
    active="active"
    size="medium"
  />
);

export const withError = () => (
  <InputFieldSelect error="The field is mandatory" size="medium" />
);
