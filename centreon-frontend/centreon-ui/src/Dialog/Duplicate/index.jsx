import React, { useState } from 'react';
import PropTypes from 'prop-types';
import TextField from '@material-ui/core/TextField';
import Dialog from '..';

function Duplicate({ labelInput, onConfirm, ...rest }) {
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
        type="number"
        color="primary"
        label={labelInput}
        onChange={handleChange}
        value={value}
        inputProps={{ min: 1 }}
        margin="dense"
        fullWidth
        autoFocus
      />
    </Dialog>
  );
}

Duplicate.propTypes = {
  open: PropTypes.bool.isRequired,
  onClose: PropTypes.func,
  onCancel: PropTypes.func.isRequired,
  onConfirm: PropTypes.func.isRequired,
  labelTitle: PropTypes.string,
  labelInput: PropTypes.string,
  labelCancel: PropTypes.string,
  labelConfirm: PropTypes.string,
};

Duplicate.defaultProps = {
  onClose: null,
  labelTitle: 'Duplicate elements',
  labelInput: 'Duplications',
  labelCancel: 'Cancel',
  labelConfirm: 'Duplicate',
};

export default Duplicate;
