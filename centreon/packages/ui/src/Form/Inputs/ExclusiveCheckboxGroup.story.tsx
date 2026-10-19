import { Typography } from '@mui/material';

import { useFormikContext } from 'formik';
import { object } from 'yup';

import { Form } from '../Form';
import { InputType } from './models';

interface Props {
  disabled?: boolean;
  notificationOptions?: Array<string> | null;
  variant?: 'checkboxes' | 'chips';
}

const ValueDisplay = (): JSX.Element => {
  const { values } = useFormikContext<{
    notificationOptions: Array<string> | null;
  }>();

  return (
    <Typography data-testid="value">
      {JSON.stringify(values.notificationOptions)}
    </Typography>
  );
};

export const Default = ({
  disabled = false,
  notificationOptions = [],
  variant
}: Props): JSX.Element => (
  <Form
    initialValues={{ notificationOptions }}
    inputs={[
      {
        dataTestId: 'notification-options',
        exclusiveCheckboxGroup: {
          direction: 'horizontal',
          exclusiveLabel: 'No notifications',
          exclusiveOption: 'None',
          options: ['Down', 'Unreachable', 'Recovery', 'None'],
          variant
        },
        fieldName: 'notificationOptions',
        getDisabled: () => disabled,
        group: '',
        label: 'Notification options',
        type: InputType.ExclusiveCheckboxGroup
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
