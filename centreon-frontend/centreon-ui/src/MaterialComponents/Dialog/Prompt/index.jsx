import React from 'react';

import PropTypes from 'prop-types';

import Button from '@material-ui/core/Button';
import DialogContentText from '@material-ui/core/DialogContentText';

import Dialog from '..';

function PromptDialog({
  confirmLabel,
  cancelLabel,
  onNoClicked,
  onYesClicked,
  children,
  info,
  ...rest
}) {
  const Body = (
    <DialogContentText>
      {info}
      {children}
    </DialogContentText>
  );

  const Actions = (
    <>
      <Button variant="contained" onClick={onYesClicked} color="primary">
        {confirmLabel}
      </Button>
      <Button variant="outlined" onClick={onNoClicked} color="primary">
        {cancelLabel}
      </Button>
    </>
  );
  return <Dialog body={Body} actions={Actions} {...rest} />;
}

PromptDialog.defaultProps = {
  confirmLabel: 'YES',
  cancelLabel: 'NO',
  children: null,
  info: '',
};

PromptDialog.propTypes = {
  confirmLabel: PropTypes.string,
  cancelLabel: PropTypes.string,
  children: PropTypes.node,
  onYesClicked: PropTypes.func.isRequired,
  onNoClicked: PropTypes.func.isRequired,
  info: PropTypes.string,
};

export default PromptDialog;
