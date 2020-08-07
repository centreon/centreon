import React from 'react';

import {
  Typography,
  makeStyles,
  ExpansionPanelSummary,
  ExpansionPanelDetails,
  ExpansionPanel,
  styled,
  withStyles,
  ListItem,
} from '@material-ui/core';
import ExpandMoreIcon from '@material-ui/icons/ExpandMore';

const useStyles = makeStyles((theme) => ({
  details: {
    padding: theme.spacing(0, 2),
  },
}));

const Title = styled(Typography)(({ theme }) => ({
  fontSize: theme.typography.pxToRem(15),
  fontWeight: 700,
}));

const Section = styled(ExpansionPanel)({
  width: '100%',
  margin: '0',
  backgroundColor: 'transparent',
  boxShadow: 'none',
  borderBottom: '1px solid #bcbdc0',
  borderRadius: '0',
});

const CustomizedExpansionPanelSummary = withStyles((theme) => ({
  root: {
    minHeight: theme.spacing(4),
    '&$expanded': {
      minHeight: theme.spacing(4),
    },
  },
  content: {
    '&$expanded': {
      margin: theme.spacing(1, 0),
    },
  },
  expanded: {},
}))(ExpansionPanelSummary);

interface Props {
  title: string;
  children: JSX.Element;
}

const ExpandableSection = ({ title, children }: Props): JSX.Element => {
  const classes = useStyles();
  return (
    <Section>
      <CustomizedExpansionPanelSummary expandIcon={<ExpandMoreIcon />}>
        <Title>{title}</Title>
      </CustomizedExpansionPanelSummary>
      <ExpansionPanelDetails className={classes.details}>
        <ListItem>{children}</ListItem>
      </ExpansionPanelDetails>
    </Section>
  );
};

export default ExpandableSection;
