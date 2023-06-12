import { useState } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { path } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography, Box } from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';

import { IconButton, TextField } from '@centreon/ui';

import { labelChangeName, labelNotificationName } from '../../translatedLabels';

import useStyles from './Header.styles';

const NotificationName = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [nameChange, setNameChange] = useState(false);

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>): void => {
    const { value } = event.target;
    setFieldValue('name', value);
  };

  const {
    setFieldValue,
    errors,
    handleBlur,
    touched,
    values: { name: notificationName }
  } = useFormikContext<FormikValues>();

  const error = path(['name'], touched) ? path(['name'], errors) : undefined;

  return (
    <Box className={classes.title}>
      {nameChange ? (
        <TextField
          required
          dataTestId={labelNotificationName}
          error={error as string | undefined}
          placeholder={t(labelNotificationName) as string}
          value={notificationName}
          onBlur={handleBlur('name')}
          onChange={handleChange}
        />
      ) : (
        <>
          <IconButton
            data-testid={t(labelChangeName)}
            title={t(labelChangeName) as string}
            onClick={(): void => setNameChange(true)}
          >
            <EditIcon />
          </IconButton>
          <Typography className={classes.name} variant="h6">
            {notificationName}
          </Typography>
        </>
      )}
    </Box>
  );
};

export default NotificationName;
