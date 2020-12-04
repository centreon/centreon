import React from 'react';

import PropTypes from 'prop-types';

import DialogContentText from '@material-ui/core/DialogContentText';

import Dialog from '..';

const Confirm = ({ labelMessage, ...rest }) => (
  <Dialog {...rest}>
    {labelMessage && <DialogContentText>{labelMessage}</DialogContentText>}
  </Dialog>
);

Confirm.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func,
  onCancel: PropTypes.func.isRequired,
  onConfirm: PropTypes.func.isRequired,
  labelTitle: PropTypes.string,
  labelMessage: PropTypes.string,
  labelCancel: PropTypes.string,
  labelConfirm: PropTypes.string,
};

Confirm.defaultProps = {
  onClose: null,
  labelTitle: 'are you sure ?',
  labelMessage: null,
  labelCancel: 'Cancel',
  labelConfirm: 'Confirm',
};

export default Confirm;
