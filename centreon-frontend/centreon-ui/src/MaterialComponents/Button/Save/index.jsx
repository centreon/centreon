import React from 'react';

import Button from '@material-ui/core/Button';
import CircularProgress from '@material-ui/core/CircularProgress';
import { createMuiTheme } from '@material-ui/core/styles';
import Tooltip from '@material-ui/core/Tooltip';
import CheckIcon from '@material-ui/icons/Check';
import SaveIcon from '@material-ui/icons/Save';
import ThemeProvider from '@material-ui/styles/ThemeProvider';
import PropTypes from 'prop-types';

const theme = createMuiTheme({
  palette: {
    primary: {
      main: '#1174cb',
    },
  },
});

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
    <Tooltip title={tooltipLabel} placement="bottom">
      <ThemeProvider theme={theme}>
        <Button
          variant="contained"
          color="primary"
          style={{ width: 40, height: 40 }}
          aria-label="save button"
          {...rest}
        >
          {ButtonContent()}
        </Button>
      </ThemeProvider>
    </Tooltip>
  );
};

SaveButton.defaultProps = {
  succeeded: false,
  disabled: false,
  loading: false,
  tooltipLabel: 'Save',
  onClick: () => {},
};

SaveButton.propTypes = {
  succeeded: PropTypes.bool,
  disabled: PropTypes.bool,
  loading: PropTypes.bool,
  tooltipLabel: PropTypes.string,
  onClick: PropTypes.func,
};

export default SaveButton;
