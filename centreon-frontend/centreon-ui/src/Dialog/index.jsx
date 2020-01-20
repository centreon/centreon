import React from 'react';

import PropTypes from 'prop-types';

import Button from '@material-ui/core/Button';
import MuiDialog from '@material-ui/core/Dialog';
import DialogTitle from '@material-ui/core/DialogTitle';
import DialogContent from '@material-ui/core/DialogContent';
import DialogActions from '@material-ui/core/DialogActions';
import { withStyles } from '@material-ui/styles';

function Dialog({
  open,
  onClose,
  onCancel,
  onConfirm,
  labelTitle,
  labelCancel,
  labelConfirm,
  children,
  contentWidth,
  confirmDisabled,
  ...rest
}) {
  return (
    <MuiDialog open={open} onClose={onClose} {...rest}>
      {labelTitle && <DialogTitle>{labelTitle}</DialogTitle>}
      {children && (
        <DialogContent style={{ overflowY: 'visible', width: contentWidth }}>
          {children}
        </DialogContent>
      )}
      <DialogActions>
        {onCancel && (
          <Button color="primary" onClick={onCancel}>
            {labelCancel}
          </Button>
        )}
        <Button color="primary" onClick={onConfirm} disabled={confirmDisabled}>
          {labelConfirm}
        </Button>
      </DialogActions>
    </MuiDialog>
  );
}

Dialog.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func,
  onCancel: PropTypes.func,
  onConfirm: PropTypes.func.isRequired,
  labelTitle: PropTypes.string,
  labelCancel: PropTypes.string,
  labelConfirm: PropTypes.string,
  children: PropTypes.node,
  contentWidth: PropTypes.number,
  confirmDisabled: PropTypes.bool,
};

Dialog.defaultProps = {
  onClose: null,
  onCancel: null,
  labelTitle: null,
  labelCancel: 'Cancel',
  labelConfirm: 'Confirm',
  children: null,
  contentWidth: null,
  confirmDisabled: false,
};

const styles = {
  paper: { overflowY: 'visible' },
};

export default withStyles(styles)(Dialog);
