import { useState } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { path, equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import EditIcon from '@mui/icons-material/Edit';
import { Box, Typography } from '@mui/material';

import { IconButton, TextField } from '@centreon/ui';

import {
  labelChangeName,
  labelName,
  labelNotificationName
} from '../../translatedLabels';
import { panelModeAtom } from '../atom';
import { PanelMode } from '../models';

import useStyles from './Header.styles';

const NotificationName = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [nameChange, setNameChange] = useState(false);
  const panelMode = useAtomValue(panelModeAtom);

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
      {nameChange || equals(panelMode, PanelMode.Create) ? (
        <TextField
          required
          ariaLabel={labelNotificationName}
          dataTestId={labelNotificationName}
          error={error as string | undefined}
          label={t(labelName) as string}
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
