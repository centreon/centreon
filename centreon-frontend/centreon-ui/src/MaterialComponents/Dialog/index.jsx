import React from 'react';

import PropTypes from 'prop-types';

import MuiDialog from '@material-ui/core/Dialog';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import DialogTitle from '@material-ui/core/DialogTitle';

function Dialog({ active, onClose, title, body, actions, ...rest }) {
  return (
    <MuiDialog
      open={active}
      onClose={onClose}
      aria-labelledby="dialog"
      {...rest}
    >
      <DialogTitle id="dialog-title">{title}</DialogTitle>
      <DialogContent>{body}</DialogContent>
      <DialogActions>{actions}</DialogActions>
    </MuiDialog>
  );
}

Dialog.defaultProps = {
  onClose: () => {},
  title: '',
};

Dialog.propTypes = {
  active: PropTypes.bool.isRequired,
  actions: PropTypes.node.isRequired,
  body: PropTypes.node.isRequired,
  onClose: PropTypes.func,
  title: PropTypes.string,
};

export default Dialog;
