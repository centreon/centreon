import { ReactNode } from 'react';

import { equals, type } from 'ramda';

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
  compact?: boolean;
  defaultExpanded?: boolean;
  title: string | JSX.Element;
}

export const CollapsibleItem = ({
  title,
  children,
  defaultExpanded,
  compact = false
}: Props): JSX.Element => {
  const { classes, cx } = useCollapsibleItemStyles();

  const isStringTitle = equals(type(title), 'String');

  return (
    <Accordion
      disableGutters
      className={classes.accordion}
      data-compact={compact}
      defaultExpanded={defaultExpanded}
    >
      <div className={classes.summaryContainer}>
        <div className={classes.customTitle}>{!isStringTitle && title}</div>
        <AccordionSummary
          classes={{
            content: cx(
              compact
                ? classes.accordionSummaryCompactContent
                : classes.accordionSummary
            ),
            root: cx(
              compact
                ? classes.accordionSummaryCompactRoot
                : classes.accordionSummaryRoot
            )
          }}
          expandIcon={<ExpandMoreIcon color="primary" />}
        >
          {isStringTitle && (
            <Typography color="primary" variant="h6">
              {title}
            </Typography>
          )}
        </AccordionSummary>
      </div>
      <AccordionDetails
        className={cx(
          compact ? classes.accordionDetailsCompact : classes.accordionDetails
        )}
      >
        {children}
      </AccordionDetails>
    </Accordion>
  );
};
