import * as React from 'react';

import {
  makeStyles,
  withStyles,
  ExpansionPanel,
  ExpansionPanelSummary as MuiExpansionPanelSummary,
  ExpansionPanelDetails as MuiExpansionPanelDetails,
} from '@material-ui/core';
import ExpandMoreIcon from '@material-ui/icons/ExpandMore';

const useStyles = makeStyles({
  filters: {
    zIndex: 4,
  },
});

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
}))(MuiExpansionPanelSummary);

const ExpansionPanelDetails = withStyles((theme) => ({
  root: {
    padding: theme.spacing(0, 0.5, 1, 2),
  },
}))(MuiExpansionPanelDetails);

export interface FiltersProps {
  filtersExpandable: boolean;
  labelFiltersIcon?: string;
  filters: React.ReactElement;
  expandableFilters?: React.ReactElement;
  onExpandTransitionFinished?: () => void;
}

const Filters = React.forwardRef(
  (
    {
      filtersExpandable,
      labelFiltersIcon,
      filters,
      expandableFilters,
      onExpandTransitionFinished,
    }: FiltersProps,
    ref,
  ): JSX.Element => {
    const [expanded, setExpanded] = React.useState(false);
    const classes = useStyles();

    const toggleExpanded = () => setExpanded(!expanded);

    return (
      <div className={classes.filters}>
        <ExpansionPanel
          square
          expanded={filtersExpandable ? expanded : false}
          onTransitionEnd={() => onExpandTransitionFinished?.()}
        >
          <ExpansionPanelSummary
            expandIcon={
              filtersExpandable && (
                <ExpandMoreIcon color="primary" aria-label={labelFiltersIcon} />
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
        </ExpansionPanel>
      </div>
    );
  },
);

export default Filters;
