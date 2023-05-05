import { FormikValues, useFormikContext } from 'formik';

import { Switch } from '@mui/material';

const ActivateAction = (): JSX.Element => {
  const { setFieldValue, values } = useFormikContext<FormikValues>();

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
    const value = event.target.checked;
    setFieldValue('isActivated', value);
  };

  return (
    <Switch
      checked={values?.isActivated}
      color="success"
      inputProps={{ 'aria-label': 'controlled' }}
      name="isActivated"
      size="small"
      onChange={handleChange}
    />
  );
};

export default ActivateAction;
