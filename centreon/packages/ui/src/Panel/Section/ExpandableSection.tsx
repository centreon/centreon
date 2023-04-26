import { makeStyles } from 'tss-react/mui';

import {
  Typography,
  AccordionSummary,
  AccordionDetails,
  Accordion,
  styled,
  AccordionProps,
  AccordionSummaryProps
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';

const useStyles = makeStyles()((theme) => ({
  details: {
    backgroundColor: theme.palette.background.paper,
    padding: theme.spacing(1, 3, 2)
  }
}));

const Title = styled(Typography)(({ theme }) => ({
  fontSize: theme.typography.pxToRem(15),
  fontWeight: 700
}));

const Section = styled((props: AccordionProps) => (
  <Accordion disableGutters square elevation={0} {...props} />
))(({ theme }) => ({
  '&:before': {
    display: 'none'
  },
  '&:not(:last-child)': {
    borderBottom: `1px solid ${theme.palette.divider}`
  },
  borderBottom: `1px solid ${theme.palette.divider}`,
  borderLeft: 'none',
  borderRight: 'none',
  borderTop: 'none'
}));

const CustomizedAccordionSummary = styled((props: AccordionSummaryProps) => (
  <AccordionSummary {...props} />
))(({ theme }) => ({
  '& .MuiAccordionSummary-content': {
    margin: theme.spacing(1)
  },
  '& .MuiAccordionSummary-expandIconWrapper': {
    transform: 'rotate(-90deg)'
  },
  '& .MuiAccordionSummary-expandIconWrapper.Mui-expanded': {
    transform: 'rotate(0deg)'
  },
  backgroundColor: theme.palette.background.paper
}));

interface Props {
  children: JSX.Element;
  title: string;
}

const ExpandableSection = ({ title, children }: Props): JSX.Element => {
  const { classes } = useStyles();

  return (
    <Section>
      <CustomizedAccordionSummary
        expandIcon={<ExpandMoreIcon color="action" />}
      >
        <Title>{title}</Title>
      </CustomizedAccordionSummary>
      <AccordionDetails className={classes.details}>
        {children}
      </AccordionDetails>
    </Section>
  );
};

export default ExpandableSection;
