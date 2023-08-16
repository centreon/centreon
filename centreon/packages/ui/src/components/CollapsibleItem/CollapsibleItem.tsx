import { ReactNode } from 'react';

import {
  AccordionDetails,
  AccordionSummary,
  Accordion,
  Typography
} from '@mui/material';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';

import { useCollapsibleItemStyles } from './useCollapsibleItemStyles';

interface Props {
  children: ReactNode;
  defaultExpanded?: boolean;
  title: string;
}

export const CollapsibleItem = ({
  title,
  children,
  defaultExpanded
}: Props): JSX.Element => {
  const { classes } = useCollapsibleItemStyles();

  return (
    <Accordion
      disableGutters
      className={classes.accordion}
      defaultExpanded={defaultExpanded}
    >
      <AccordionSummary
        classes={{
          content: classes.accordionSummary
        }}
        expandIcon={<ExpandMoreIcon color="primary" />}
      >
        <Typography color="primary">
          <strong>{title}</strong>
        </Typography>
      </AccordionSummary>
      <AccordionDetails className={classes.accordionDetails}>
        {children}
      </AccordionDetails>
    </Accordion>
  );
};
