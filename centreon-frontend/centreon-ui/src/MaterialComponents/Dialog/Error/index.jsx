import React from 'react';

import red from '@material-ui/core/colors/red';
import styled from '@emotion/styled';
import Button from '@material-ui/core/Button';
import DialogContentText from '@material-ui/core/DialogContentText';
import PropTypes from 'prop-types';
import Dialog from '..';

import IconError from '../../Icons/IconError';

const errorRed = red[900];

const RedErrorIcon = styled(IconError)(() => ({
  color: errorRed,
  marginRight: 10,
}));

function ErrorDialog({ confirmLabel, onClose, info, ...rest }) {
  const Body = (
    <DialogContentText>
      <RedErrorIcon />
      {info}
    </DialogContentText>
  );

  const Actions = (
    <Button onClick={onClose} color="primary" autoFocus>
      {confirmLabel}
    </Button>
  );

  return (
    <Dialog transitionDuration={0} body={Body} actions={Actions} {...rest} />
  );
}

ErrorDialog.propTypes = {
  info: PropTypes.string.isRequired,
  confirmLabel: PropTypes.string.isRequired,
  onClose: PropTypes.func.isRequired,
};

export default ErrorDialog;
