import { inc } from 'ramda';
import { Link } from 'react-router';

import { Tooltip } from '@centreon/ui/components';
import { Typography } from '@mui/material';
import { getResourcesUrlForMetricsWidgets } from '../../utils';

import { forwardRef } from 'react';
import { useTopBottomStyles } from './TopBottom.styles';
import { Resource } from './models';

interface Props {
  metricTop: Resource;
  index: number;
}

const Label = forwardRef<HTMLParagraphElement, Props>(
  ({ metricTop, index }, ref) => {
    const { classes } = useTopBottomStyles({});
    const prefix = `#${inc(index)}`;
    const title = `${prefix} ${metricTop.parentName}_${metricTop.name}`;

    return (
      <div className={classes.tooltipContainer}>
        <Tooltip
          followCursor={false}
          label={`${metricTop.parentName}_${metricTop.name}`}
          placement="top"
        >
          <Typography className={classes.resourceLabel} ref={ref}>
            <Link
              className={classes.linkToResourcesStatus}
              data-testid={`link to ${metricTop?.name}`}
              target="_blank"
              to={getResourcesUrlForMetricsWidgets(metricTop)}
            >
              <strong>{title}</strong>
            </Link>
          </Typography>
        </Tooltip>
      </div>
    );
  }
);

export default Label;
