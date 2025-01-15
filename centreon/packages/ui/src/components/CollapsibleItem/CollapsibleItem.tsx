import { ReactNode } from 'react';

import { equals, type } from 'ramda';

import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import {
  Accordion,
  AccordionDetails,
  AccordionSummary,
  Typography
} from '@mui/material';

import { useCollapsibleItemStyles } from './useCollapsibleItemStyles';

export interface Props {
  children: ReactNode;
  compact?: boolean;
  dataTestId?: string;
  defaultExpanded?: boolean;
  title: string | JSX.Element;
}

export const CollapsibleItem = ({
  title,
  children,
  defaultExpanded,
  compact = false,
  dataTestId = ''
}: Props): JSX.Element => {
  const { classes, cx } = useCollapsibleItemStyles();

  const isStringTitle = equals(type(title), 'String');

  return (
    <Accordion
      disableGutters
      className={classes.accordion}
      data-compact={compact}
      data-testid={`${dataTestId}-accordion`}
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
          data-testid={`${dataTestId}-summary`}
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
