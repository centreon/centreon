import * as React from 'react';

import {
  withStyles,
  Accordion,
  AccordionSummary,
  AccordionDetails,
} from '@material-ui/core';
import ExpandMoreIcon from '@material-ui/icons/ExpandMore';

const ExpansionPanelSummary = withStyles((theme) => ({
  root: {
    padding: theme.spacing(0, 3, 0, 2),
    minHeight: 'auto',
    '&$expanded': {
      minHeight: 'auto',
    },
    '&$focused': {
      backgroundColor: 'unset',
    },
    justifyContent: 'flex-start',
  },
  content: {
    margin: theme.spacing(1, 0),
    '&$expanded': {
      margin: theme.spacing(1, 0),
    },
    flexGrow: 0,
  },
  focused: {},
  expanded: {},
}))(AccordionSummary);

const ExpansionPanelDetails = withStyles((theme) => ({
  root: {
    padding: theme.spacing(0, 0.5, 1, 2),
  },
}))(AccordionDetails);

export interface FiltersProps {
  expandable?: boolean;
  expandLabel?: string;
  filters: React.ReactElement;
  expandableFilters?: React.ReactElement;
  onExpandTransitionFinish?: (expanded: boolean) => void;
}

const Filters = React.forwardRef(
  (
    {
      expandable = false,
      expandLabel,
      filters,
      expandableFilters,
      onExpandTransitionFinish,
    }: FiltersProps,
    ref,
  ): JSX.Element => {
    const [expanded, setExpanded] = React.useState(false);

    const toggleExpanded = () => setExpanded(!expanded);

    return (
      <Accordion
        square
        expanded={expandable ? expanded : false}
        onTransitionEnd={() => onExpandTransitionFinish?.(expanded)}
      >
        <ExpansionPanelSummary
          expandIcon={
            expandable && (
              <ExpandMoreIcon color="primary" aria-label={expandLabel} />
            )
          }
          IconButtonProps={{ onClick: toggleExpanded }}
          style={{ cursor: 'default' }}
          ref={ref as React.RefObject<HTMLDivElement>}
        >
          {filters}
        </ExpansionPanelSummary>
        {expandableFilters && (
          <ExpansionPanelDetails>{expandableFilters}</ExpansionPanelDetails>
        )}
      </Accordion>
    );
  },
);

export default Filters;
