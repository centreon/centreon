import React, { useState } from 'react';
import PropTypes from 'prop-types';
import TextField from '@material-ui/core/TextField';
import Dialog from '../..';

function MassiveChangeThresholds({
  onConfirm,
  labelTitle,
  labelWarning,
  labelCritical,
  ...rest
}) {
  const [warning, setWarning] = useState(0);
  const [critical, setCritical] = useState(0);

  const handleWarningChange = ({ target }) => {
    if (target.value <= 100 && target.value >= 0) {
      setWarning(target.value);
    }
  };

  const handleCriticalChange = ({ target }) => {
    if (target.value <= 100 && target.value >= 0) {
      setCritical(target.value);
    }
  };

  const handleConfirm = (event) => {
    onConfirm(event, { warning, critical });
  };

  return (
    <Dialog onConfirm={handleConfirm} labelTitle={labelTitle} {...rest}>
      <TextField
        type="number"
        label={labelWarning}
        onChange={handleWarningChange}
        value={warning}
        inputProps={{ min: 0, max: 100 }}
        margin="dense"
        placeholder="0-100%"
        fullWidth
        autoFocus
      />
      <TextField
        type="number"
        label={labelCritical}
        onChange={handleCriticalChange}
        value={critical}
        inputProps={{ min: 0, max: 100 }}
        margin="dense"
        placeholder="0-100%"
        fullWidth
      />
    </Dialog>
  );
}

MassiveChangeThresholds.propTypes = {
  onConfirm: PropTypes.func.isRequired,
  labelTitle: PropTypes.string,
  labelWarning: PropTypes.string,
  labelCritical: PropTypes.string,
};

MassiveChangeThresholds.defaultProps = {
  labelTitle: 'Massive change of thresholds',
  labelWarning: 'Warning threshold',
  labelCritical: 'Critical threshold',
};

export default MassiveChangeThresholds;
