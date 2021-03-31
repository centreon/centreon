import React from 'react';

import PropTypes from 'prop-types';

import Button from '@material-ui/core/Button';
import CircularProgress from '@material-ui/core/CircularProgress';
import Tooltip from '@material-ui/core/Tooltip';
import CheckIcon from '@material-ui/icons/Check';
import SaveIcon from '@material-ui/icons/Save';

const SaveButton = ({ succeeded, loading, tooltipLabel, ...rest }) => {
  const ButtonContent = () => {
    if (succeeded) {
      return <CheckIcon />;
    }

    if (loading) {
      return <CircularProgress size={30} />;
    }

    return <SaveIcon />;
  };

  return (
    <Tooltip placement="bottom" title={tooltipLabel}>
      <div>
        <Button
          aria-label="save button"
          color="primary"
          style={{ height: 40, width: 40 }}
          variant="contained"
          {...rest}
        >
          {ButtonContent()}
        </Button>
      </div>
    </Tooltip>
  );
};

SaveButton.defaultProps = {
  disabled: false,
  loading: false,
  onClick: () => {},
  succeeded: false,
  tooltipLabel: 'Save',
};

SaveButton.propTypes = {
  disabled: PropTypes.bool,
  loading: PropTypes.bool,
  onClick: PropTypes.func,
  succeeded: PropTypes.bool,
  tooltipLabel: PropTypes.string,
};

export default SaveButton;
