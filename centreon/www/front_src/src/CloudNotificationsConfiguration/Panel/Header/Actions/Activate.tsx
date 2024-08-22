import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { Switch as MUISwitch, Tooltip } from '@mui/material';
import { styled } from '@mui/material/styles';

import { labelActiveOrInactive } from '../../../translatedLabels';

const Switch = styled(MUISwitch)(({ theme }) => ({
  '& .MuiSwitch-switchBase': {
    '&.Mui-checked': {
      '& + .MuiSwitch-track': {
        backgroundColor: theme.palette.success.main,
        opacity: 1
      },
      color: theme.palette.common.white
    }
  },
  '& .MuiSwitch-thumb': {
    backgroundColor: theme.palette.common.white
  }
}));

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
        name="isActivated"
        size="small"
        onChange={handleChange}
      />
    </Tooltip>
  );
};

export default ActivateAction;
