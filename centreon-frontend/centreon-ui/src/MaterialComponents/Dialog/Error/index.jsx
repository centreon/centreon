import React from 'react';

import Dialog from '@material-ui/core/Dialog';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import DialogContentText from '@material-ui/core/DialogContentText';
import DialogTitle from '@material-ui/core/DialogTitle';
import red from '@material-ui/core/colors/red';
import styled from '@emotion/styled';
import Button from '@material-ui/core/Button';
import PropTypes from 'prop-types';

import IconError from '../../Icons/IconError';

const errorRed = red[900];

const RedErrorIcon = styled(IconError)(() => ({
  color: errorRed,
  marginRight: 10,
}));

function ErrorDialog({ open, title, text, confirmLabel, onClose }) {
  return (
    <Dialog transitionDuration={0} open={open} aria-labelledby="error-dialog">
      <DialogTitle id="error-dialog-title">{title}</DialogTitle>
      <DialogContent>
        <DialogContentText>
          <RedErrorIcon />
          {text}
        </DialogContentText>
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose} color="primary" autoFocus>
          {confirmLabel}
        </Button>
      </DialogActions>
    </Dialog>
  );
}

ErrorDialog.propTypes = {
  open: PropTypes.bool.isRequired,
  title: PropTypes.string.isRequired,
  text: PropTypes.string.isRequired,
  confirmLabel: PropTypes.string.isRequired,
  onClose: PropTypes.func.isRequired,
};

export default ErrorDialog;
