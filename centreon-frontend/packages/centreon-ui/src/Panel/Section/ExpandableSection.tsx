import React from 'react';

import {
  Typography,
  AccordionSummary,
  AccordionDetails,
  Accordion,
  styled,
  ListItem,
} from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import withStyles from '@mui/styles/withStyles';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';

const useStyles = makeStyles((theme) => ({
  details: {
    padding: theme.spacing(0, 2),
  },
}));

const Title = styled(Typography)(({ theme }) => ({
  fontSize: theme.typography.pxToRem(15),
  fontWeight: 700,
}));

const Section = styled(Accordion)({
  backgroundColor: 'transparent',
  borderBottom: '1px solid #bcbdc0',
  borderRadius: '0',
  boxShadow: 'none',
  margin: '0',
  width: '100%',
});

const CustomizedAccordionSummary = withStyles((theme) => ({
  content: {
    '&$expanded': {
      margin: theme.spacing(1, 0),
    },
  },
  expanded: {},
  root: {
    '&$expanded': {
      minHeight: theme.spacing(4),
    },
    minHeight: theme.spacing(4),
  },
}))(AccordionSummary);

interface Props {
  children: JSX.Element;
  title: string;
}

const ExpandableSection = ({ title, children }: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <Section>
      <CustomizedAccordionSummary expandIcon={<ExpandMoreIcon />}>
        <Title>{title}</Title>
      </CustomizedAccordionSummary>
      <AccordionDetails className={classes.details}>
        <ListItem>{children}</ListItem>
      </AccordionDetails>
    </Section>
  );
};

export default ExpandableSection;
