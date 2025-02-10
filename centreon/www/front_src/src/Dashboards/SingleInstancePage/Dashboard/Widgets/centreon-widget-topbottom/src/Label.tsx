import { inc } from 'ramda';
import { Link } from 'react-router';

import { Tooltip } from '@centreon/ui/components';
import { Typography } from '@mui/material';
import { getResourcesUrlForMetricsWidgets } from '../../utils';

import { forwardRef } from 'react';
import { useTopBottomStyles } from './TopBottom.styles';

const Label = forwardRef(({ metricTop, index }, ref) => {
  const { classes } = useTopBottomStyles();

  return (
    <div
      style={{
        display: 'flex',
        alignItems: 'end',
        height: 50,
        marginRight: 24
      }}
    >
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
            <strong>
              #{inc(index)} {`${metricTop.parentName}_${metricTop.name}`}
            </strong>
          </Link>
        </Typography>
      </Tooltip>
    </div>
  );
});

export default Label;
