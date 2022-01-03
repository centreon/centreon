import React, { useState } from 'react';

import PropTypes from 'prop-types';

import TextField from '@mui/material/TextField';

import Dialog from '..';

const Duplicate = ({ labelInput, onConfirm, ...rest }) => {
  const [value, setValue] = useState(1);

  const handleChange = ({ target }) => {
    setValue(target.value);
  };

  const handleConfirm = (event) => {
    onConfirm(event, value);
  };

  return (
    <Dialog maxWidth="xs" onConfirm={handleConfirm} {...rest}>
      <TextField
        autoFocus
        fullWidth
        color="primary"
        inputProps={{ 'aria-label': 'Duplications', min: 1 }}
        label={labelInput}
        margin="dense"
        type="number"
        value={value}
        onChange={handleChange}
      />
    </Dialog>
  );
};

Duplicate.propTypes = {
  labelCancel: PropTypes.string,
  labelConfirm: PropTypes.string,
  labelInput: PropTypes.string,
  labelTitle: PropTypes.string,
  onCancel: PropTypes.func.isRequired,
  onClose: PropTypes.func,
  onConfirm: PropTypes.func.isRequired,
  open: PropTypes.bool.isRequired,
};

Duplicate.defaultProps = {
  labelCancel: 'Cancel',
  labelConfirm: 'Duplicate',
  labelInput: 'Duplications',
  labelTitle: 'Duplicate elements',
  onClose: null,
};

export default Duplicate;
