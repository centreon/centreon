import React from 'react';
import Button from '@material-ui/core/Button';
import DialogContentText from '@material-ui/core/DialogContentText';
import PropTypes from 'prop-types';

import Dialog from '..';

function MassiveChangeDialog({
  onNoClicked,
  onYesClicked,
  children,
  info,
  applyLabel = 'Apply',
  cancelLabel = 'Cancel',
  ...rest
}) {
  const Body = (
    <DialogContentText>
      {info}
      {children}
    </DialogContentText>
  );

  const Actions = (
    <React.Fragment>
      <Button variant="contained" color="primary" onClick={onYesClicked}>
        {applyLabel}
      </Button>

      <Button variant="outlined" onClick={onNoClicked} color="primary">
        {cancelLabel}
      </Button>
    </React.Fragment>
  );
  return <Dialog body={Body} actions={Actions} {...rest} />;
}

MassiveChangeDialog.defaultProps = {
  cancelLabel: 'Cancel',
  applyLabel: 'Apply',
  children: null,
};

MassiveChangeDialog.propTypes = {
  children: PropTypes.oneOfType([
    PropTypes.arrayOf(PropTypes.node),
    PropTypes.node,
  ]),
  onNoClicked: PropTypes.func.isRequired,
  onYesClicked: PropTypes.func.isRequired,
  info: PropTypes.string.isRequired,
  applyLabel: PropTypes.string,
  cancelLabel: PropTypes.string,
};

export default MassiveChangeDialog;
