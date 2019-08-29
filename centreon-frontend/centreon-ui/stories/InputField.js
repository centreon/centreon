/* eslint-disable prettier/prettier */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable import/no-extraneous-dependencies */
import React from 'react';
import { storiesOf } from '@storybook/react';
import classnames from 'classnames';
import styles from '../src/InputField/InputFieldSelect/input-field-select.scss';
import {
  InputField,
  InputFieldSelect,
  InputFieldTextarea,
  InputFieldMultiSelect,
  InputFieldSelectCustom,
} from '../src';
import InputSearch from '../src/MaterialComponents/Input/Search';

storiesOf('Input Field', module).add(
  'Input Field - with title',
  () => (
    <InputField
      type="text"
      label="Input field with title"
      name="test"
      inputSize="small"
    />
  ),
  { notes: 'A very simple component' },
);

storiesOf('Input Field', module).add(
  'Input Field - without title',
  () => <InputField type="text" name="test" inputSize="small" />,
  { notes: 'A very simple component' },
);

storiesOf('Input Field', module).add(
  'Input Field - without title',
  () => <InputField type="text" name="test" inputSize="small" />,
  { notes: 'A very simple component' },
);

storiesOf('Input Field', module).add(
  'Input Field error - without title',
  () => (
    <InputField
      type="text"
      name="test"
      error="The field is mandatory"
      inputSize="small"
    />
  ),
  { notes: 'A very simple component' },
);

storiesOf('Input Field', module).add(
  'Input Field - select',
  () => (
    <InputFieldSelect
      customClass={classnames(styles['select-option-custom'])}
    />
  ),
  { notes: 'A very simple component' },
);

storiesOf('Input Field', module).add(
  'Input Field - textarea',
  () => (
    <InputFieldTextarea textareaType="small" label="Textarea field label" />
  ),
  { notes: 'A very simple component' },
);

storiesOf('Input Field', module).add(
  'Input Field - multiselect custom',
  () => (
    <InputFieldMultiSelect
      options={[
        { id: '1', name: '24x7', alias: 'Always' },
        { id: '2', name: 'none', alias: 'Never' },
        { id: '3', name: 'nonworkhours', alias: 'Non-Work Hours' },
        { id: '4', name: 'workhours', alias: 'Work hours' },
      ]}
      size="medium"
    />
  ),
  { notes: 'A very simple component' },
);

storiesOf('Input Field', module).add(
  'Input Field - multiselect custom error',
  () => <InputFieldMultiSelect error="The field is mandatory" size="medium" />,
  { notes: 'A very simple component' },
);

storiesOf('Input Field', module).add(
  'Input Field - select custom',
  () => (
    <InputFieldSelectCustom
      options={[
        { id: '1', name: '24x7', alias: 'Always' },
        { id: '2', name: 'none', alias: 'Never' },
        { id: '3', name: 'nonworkhours', alias: 'Non-Work Hours' },
        { id: '4', name: 'workhours', alias: 'Work hours' },
      ]}
      active="active"
      size="medium"
    />
  ),
  { notes: 'A very simple component' },
);

storiesOf('Input Field', module).add(
  'Input Field - select custom error',
  () => <InputFieldSelectCustom error="The field is mandatory" size="medium" />,
  { notes: 'A very simple component' },
);

storiesOf('Input Field', module).add(
  'Input Field - search',
  () => <InputSearch />,
);
