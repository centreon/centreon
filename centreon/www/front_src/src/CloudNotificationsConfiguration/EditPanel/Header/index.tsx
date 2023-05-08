import { useState } from 'react';

import { makeStyles } from 'tss-react/mui';
import { useAtom } from 'jotai';
import { FormikValues, useFormikContext } from 'formik';

import { Typography, Box } from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';

import { IconButton, TextField } from '@centreon/ui';

import { notificationNameAtom } from '../atom';

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
  const { classes } = useStyle();
  const [nameChange, setNameChange] = useState(false);
  const [notificationName, setNotificationName] = useAtom(notificationNameAtom);

  const handleNameChange = (
    event: React.ChangeEvent<HTMLInputElement>
  ): void => {
    const { value } = event.target;
    setNotificationName(value);
    setFieldValue('name', value);
  };

  const { setFieldValue } = useFormikContext<FormikValues>();

  return (
    <Box className={classes.panelHeader}>
      <Box className={classes.title}>
        <IconButton
          title="Change the name"
          onClick={(): void => setNameChange(true)}
        >
          <EditIcon />
        </IconButton>
        {nameChange ? (
          <TextField value={notificationName} onChange={handleNameChange} />
        ) : (
          <Typography className={classes.name} variant="h6">
            {notificationName}
          </Typography>
        )}
      </Box>
      <Box className={classes.rightHeader}>
        <Box className={classes.actions}>
          <ActivateAction />
          <DuplicateAction />
          <SaveAction />
          <DeleteAction />
        </Box>
        <ClosePanelAction />
      </Box>
    </Box>
  );
};

export default Header;
