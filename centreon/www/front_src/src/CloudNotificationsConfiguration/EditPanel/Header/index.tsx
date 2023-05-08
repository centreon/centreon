import { useState } from 'react';

import { makeStyles } from 'tss-react/mui';
import { useAtomValue } from 'jotai';
import { FormikValues, useFormikContext } from 'formik';
import { equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Typography, Box } from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';

import { IconButton, TextField } from '@centreon/ui';

import { panelModeAtom } from '../atom';
import { PanelMode } from '../models';
import { labelChangeName, labelNotificationName } from '../../translatedLabels';

import {
  DeleteAction,
  DuplicateAction,
  ActivateAction,
  ClosePanelAction,
  SaveAction
} from './Actions';

const useStyle = makeStyles()((theme) => ({
  actions: {
    alignItems: 'center',
    borderRight: '1px dotted black',
    display: 'flex',
    gap: theme.spacing(2),
    paddingInline: theme.spacing(2)
  },
  name: {
    fontWeight: theme.typography.fontWeightBold
  },
  panelHeader: {
    background: theme.palette.background.paper,
    boxSizing: 'border-box',
    display: 'flex',
    justifyContent: 'space-between',
    padding: theme.spacing(1.5, 0),
    paddingLeft: theme.spacing(2)
  },
  rightHeader: {
    alignItems: 'center',
    display: 'flex'
  },
  title: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1)
  }
}));

const Header = (): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = useStyle();

  const [nameChange, setNameChange] = useState(false);
  const panelMode = useAtomValue(panelModeAtom);

  const handleNameChange = (
    event: React.ChangeEvent<HTMLInputElement>
  ): void => {
    const { value } = event.target;
    setFieldValue('name', value);
  };

  const {
    setFieldValue,
    isValid,
    errors,
    values: { name: notificationName }
  } = useFormikContext<FormikValues>();

  const error = isEmpty(notificationName) ? errors?.name : undefined;

  return (
    <Box className={classes.panelHeader}>
      <Box className={classes.title}>
        {nameChange ? (
          <TextField
            required
            dataTestId=""
            error={error as string | undefined}
            name="name"
            placeholder={t(labelNotificationName) as string}
            value={notificationName}
            onChange={handleNameChange}
          />
        ) : (
          <>
            <IconButton
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
      <Box className={classes.rightHeader}>
        <Box className={classes.actions}>
          <ActivateAction />
          {equals(panelMode, PanelMode.Edit) && <DuplicateAction />}
          <SaveAction isValid={isValid} />
          {equals(panelMode, PanelMode.Edit) && <DeleteAction />}
        </Box>
        <ClosePanelAction />
      </Box>
    </Box>
  );
};

export default Header;
