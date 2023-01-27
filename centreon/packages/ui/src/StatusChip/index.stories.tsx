import { ComponentMeta, ComponentStory } from '@storybook/react';
import { makeStyles } from 'tss-react/mui';

import { Theme } from '@mui/material';

import StatusChip, { SeverityCode } from '.';

const useStyles = makeStyles()((theme: Theme) => ({
  root: {
    '&:hover': {
      background: theme.palette.primary.dark,
      cursor: 'pointer'
    },
    background: theme.palette.primary.light,
    borderRadius: 0,
    width: theme.spacing(50)
  }
}));

export default {
  argTypes: {
    clickable: { control: 'boolean' },
    label: { control: 'text' }
  },
  component: StatusChip,
  title: 'StatusChip'
} as ComponentMeta<typeof StatusChip>;

const TemplateStatusChip: ComponentStory<typeof StatusChip> = (args) => (
  <StatusChip {...args} />
);

export const PlaygroundStatusChip = TemplateStatusChip.bind({});
PlaygroundStatusChip.args = {
  clickable: true,
  label: 'Status CHip Label'
};

export const withOkSeverityCode = (): JSX.Element => (
  <StatusChip label="Up" severityCode={SeverityCode.Ok} />
);

export const withMediumSeverityCode = (): JSX.Element => (
  <StatusChip label="Warning" severityCode={SeverityCode.Medium} />
);

export const withHighSeverityCode = (): JSX.Element => (
  <StatusChip label="Down" severityCode={SeverityCode.High} />
);

export const withNoneSeverityCode = (): JSX.Element => (
  <StatusChip label="Unknown" severityCode={SeverityCode.None} />
);

export const withPendingSeverityCode = (): JSX.Element => (
  <StatusChip label="Pending" severityCode={SeverityCode.Pending} />
);

export const withHighSeverityCodeAndWithoutLabel = (): JSX.Element => (
  <StatusChip severityCode={SeverityCode.High} />
);

export const withNoneSeverityCodeAndWithoutLabel = (): JSX.Element => (
  <StatusChip severityCode={SeverityCode.None} />
);

const CustomStatusShip = (): JSX.Element => {
  const { classes } = useStyles();

  return (
    <StatusChip
      className={classes.root}
      label="Custom ship"
      severityCode={SeverityCode.Pending}
    />
  );
};

export const customStatusShip = (): JSX.Element => <CustomStatusShip />;
