import React from 'react';
import PropTypes from 'prop-types';
import {
  createMuiTheme,
  ThemeProvider as MuiThemeProvider,
} from '@material-ui/core/styles';

const theme = createMuiTheme({
  palette: {
    primary: {
      main: '#009fdf',
    },
  },
});

function ThemeProvider({ children, ...rest }) {
  return (
    <MuiThemeProvider theme={theme} {...rest}>
      {children}
    </MuiThemeProvider>
  );
}

ThemeProvider.propTypes = {
  children: PropTypes.node.isRequired,
};

ThemeProvider.defaultProps = {};

export default ThemeProvider;
