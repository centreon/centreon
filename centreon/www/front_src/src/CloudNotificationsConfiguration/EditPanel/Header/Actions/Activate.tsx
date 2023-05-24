import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { Switch, Tooltip } from '@mui/material';

import { labelActiveOrInactive } from '../../../translatedLabels';

const ActivateAction = (): JSX.Element => {
  const { t } = useTranslation();

  const { setFieldValue, values } = useFormikContext<FormikValues>();

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
    const value = event.target.checked;
    setFieldValue('isActivated', value);
  };

  return (
    <Tooltip title={t(labelActiveOrInactive)}>
      <Switch
        ariaLabel={t(labelActiveOrInactive) as string}
        checked={values?.isActivated}
        color="success"
        inputProps={{ 'aria-label': 'controlled' }}
        name="isActivated"
        size="small"
        onChange={handleChange}
      />
    </Tooltip>
  );
};

export default ActivateAction;
