import React from 'react';

import Typography from '@material-ui/core/Typography';

import { Wizard, WizardPage as Page } from '..';

export default { title: 'Wizard' };

export const oneStep = () => (
  <Wizard open>
    <Page>
      <Typography variant="h5" align="center">
        Step 1
      </Typography>
    </Page>
  </Wizard>
);

export const threeSteps = () => (
  <Wizard open>
    <Page label="step 1">
      <Typography variant="h5" align="center">
        Step 1
      </Typography>
    </Page>
    <Page label="step 2">
      <Typography variant="h5" align="center">
        Step 2
      </Typography>
    </Page>
    <Page label="step3">
      <Typography variant="h5" align="center">
        Step 3
      </Typography>
    </Page>
  </Wizard>
);

export const fullHeight = () => (
  <Wizard open fullHeight>
    <Page>
      <Typography variant="h5" align="center">
        Step 1
      </Typography>
    </Page>
    <Page>
      <Typography variant="h5" align="center">
        Step 2
      </Typography>
    </Page>
    <Page>
      <Typography variant="h5" align="center">
        Step 3
      </Typography>
    </Page>
  </Wizard>
);

export const smallWidth = () => (
  <Wizard open width="xs">
    <Page>
      <Typography variant="h5" align="center">
        Step 1
      </Typography>
    </Page>
    <Page>
      <Typography variant="h5" align="center">
        Step 2
      </Typography>
    </Page>
    <Page>
      <Typography variant="h5" align="center">
        Step 3
      </Typography>
    </Page>
  </Wizard>
);

export const withCustomExitAlertLabels = () => (
  <Wizard
    open
    exitConfirmProps={{
      labelTitle: 'Exit wizard ?',
      labelMessage: "Wizard's progress will not be saved",
      labelConfirm: 'Exit',
    }}
  >
    <Page>
      <Typography variant="h5" align="center">
        Step 1
      </Typography>
    </Page>
    <Page>
      <Typography variant="h5" align="center">
        Step 2
      </Typography>
    </Page>
    <Page>
      <Typography variant="h5" align="center">
        Step 3
      </Typography>
    </Page>
  </Wizard>
);
