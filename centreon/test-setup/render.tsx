import { Box, CssBaseline } from '@mui/material';

import { ThemeProvider } from '@centreon/ui';

import {
  type RenderResult,
  render as renderWithTestingLibrary
} from '@testing-library/react';
import type { ReactNode } from 'react';

// The rstest counterpart of `cy.mount`: the component inside the Centreon
// theme, on the paper background.
export const render = (component: ReactNode): RenderResult =>
  renderWithTestingLibrary(
    <ThemeProvider>
      <Box
        sx={{
          backgroundColor: 'background.paper',
          height: '100%',
          width: '100%'
        }}
      >
        {component}
      </Box>
      <CssBaseline />
    </ThemeProvider>
  );
