import React from 'react';
import { storiesOf } from '@storybook/react';
import Typography from '@material-ui/core/Typography';
import { Wizard, WizardPage as Page } from '../src';

storiesOf('Wizard', module).add('with three labeled steps', () => (
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
));

storiesOf('Wizard', module).add('with full height', () => (
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
));

storiesOf('Wizard', module).add('with small width (xs)', () => (
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
));

storiesOf('Wizard', module).add('with custom exit alert labels', () => (
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
));
