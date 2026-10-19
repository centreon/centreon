import { Typography } from '@mui/material';

import { useFormikContext } from 'formik';
import { object } from 'yup';

import { Form } from '../Form';
import { InputType } from './models';

interface Props {
  disabled?: boolean;
  enabled?: string;
}

const options = [
  { label: 'Yes', value: 'true' },
  { label: 'No', value: 'false' },
  { label: 'Default', value: 'use_default' }
];

const ValueDisplay = (): JSX.Element => {
  const { values } = useFormikContext<{ enabled: string }>();

  return (
    <Typography data-testid="value">
      {JSON.stringify(values.enabled)}
    </Typography>
  );
};

export const Default = ({
  disabled = false,
  enabled = 'use_default'
}: Props): JSX.Element => (
  <Form
    initialValues={{ enabled }}
    inputs={[
      {
        dataTestId: 'enabled',
        fieldName: 'enabled',
        getDisabled: () => disabled,
        group: '',
        label: 'Notification Enabled',
        segmentedButtons: { options },
        type: InputType.SegmentedButtons
      },
      {
        custom: { Component: ValueDisplay },
        fieldName: 'value',
        group: '',
        label: 'value',
        type: InputType.Custom
      }
    ]}
    submit={() => undefined}
    validationSchema={object()}
  />
);
