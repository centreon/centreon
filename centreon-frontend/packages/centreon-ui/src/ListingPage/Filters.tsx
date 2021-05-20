import * as React from 'react';

import { isNil } from 'ramda';

import {
  withStyles,
  Accordion,
  AccordionSummary,
  AccordionDetails,
  useTheme,
} from '@material-ui/core';
import ExpandMoreIcon from '@material-ui/icons/ExpandMore';

import { useMemoComponent } from '..';

const ExpansionPanelSummary = withStyles((theme) => ({
  content: {
    '&$expanded': {
      margin: theme.spacing(1, 0),
    },
    flexGrow: 0,
    margin: theme.spacing(1, 0),
  },
  expanded: {},
  focused: {},
  root: {
    '&$expanded': {
      minHeight: 'auto',
    },
    '&$focused': {
      backgroundColor: 'unset',
    },
    justifyContent: 'flex-start',
    minHeight: 'auto',
    padding: theme.spacing(0, 3, 0, 2),
  },
}))(AccordionSummary);

const ExpansionPanelDetails = withStyles((theme) => ({
  root: {
    padding: theme.spacing(0, 0.5, 1, 2),
  },
}))(AccordionDetails);

export interface FiltersProps {
  expandLabel?: string;
  expandableFilters?: React.ReactElement;
  expanded?: boolean;
  filters?: React.ReactElement;
  onExpand?: () => void;
}

const Filters = React.forwardRef(
  (
    {
      expandLabel,
      expanded = false,
      onExpand,
      filters,
      expandableFilters,
    }: FiltersProps,
    ref,
  ): JSX.Element => {
    const expandable = !isNil(onExpand);
    const theme = useTheme();

    return (
      <Accordion square expanded={expandable ? expanded : false}>
        <ExpansionPanelSummary
          IconButtonProps={{
            onClick: onExpand,
            style: { padding: theme.spacing(1) },
          }}
          expandIcon={expandable && <ExpandMoreIcon aria-label={expandLabel} />}
          ref={ref as React.RefObject<HTMLDivElement>}
          style={{ cursor: 'default' }}
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

interface MemoizedFiltersProps extends FiltersProps {
  memoProps?: Array<unknown>;
}

export const MemoizedFilters = ({
  memoProps = [],
  expanded,
  ...props
}: MemoizedFiltersProps): JSX.Element =>
  useMemoComponent({
    Component: <Filters expanded={expanded} {...props} />,
    memoProps: [...memoProps, expanded],
  });

export default Filters;
