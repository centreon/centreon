import React from 'react';

import PropTypes from 'prop-types';

import DialogContentText from '@mui/material/DialogContentText';

import Dialog from '..';

const Confirm = ({ labelMessage, ...rest }) => (
  <Dialog {...rest}>
    {labelMessage && <DialogContentText>{labelMessage}</DialogContentText>}
  </Dialog>
);

Confirm.propTypes = {
  labelCancel: PropTypes.string,
  labelConfirm: PropTypes.string,
  labelMessage: PropTypes.string,
  labelTitle: PropTypes.string,
  onCancel: PropTypes.func.isRequired,
  onClose: PropTypes.func,
  onConfirm: PropTypes.func.isRequired,
  open: PropTypes.bool.isRequired,
};

Confirm.defaultProps = {
  labelCancel: 'Cancel',
  labelConfirm: 'Confirm',
  labelMessage: null,
  labelTitle: 'are you sure ?',
  onClose: null,
};

export default Confirm;
