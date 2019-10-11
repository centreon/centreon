import React, { useState } from 'react';
import TextField from '@material-ui/core/TextField';

import PropTypes from 'prop-types';
import PromptDialog from '..';

function PromptNumberDialog({ onYesClicked, ...rest }) {
  const [value, setValue] = useState(1);

  const confirm = () => {
    onYesClicked(value);
  };

  const changeValue = ({ target }) => {
    setValue(target.value);
  };

  return (
    <div>
      <PromptDialog onYesClicked={confirm} {...rest}>
        <TextField
          autoFocus
          onChange={changeValue}
          margin="dense"
          id="prompt-dialog-count"
          type="number"
          value={value}
          defaultValue={1}
          inputProps={{ min: 1 }}
          fullWidth
        />
      </PromptDialog>
    </div>
  );
}

PromptNumberDialog.defaultProps = {
  confirmLabel: 'OK',
  cancelLabel: 'Cancel',
};

PromptNumberDialog.propTypes = {
  onYesClicked: PropTypes.func.isRequired,
  confirmLabel: PropTypes.string,
  cancelLabel: PropTypes.string,
};

export default PromptNumberDialog;
