import { Link } from 'react-router-dom';

import { Typography } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import TooltipContent from '../Tooltip/Tooltip';
import { FormattedResponse, getValueByUnit } from '../utils';
import { Resource } from '../../../models';

import { useLegendStyles } from './Legend.styles';

interface Props {
  data: Array<FormattedResponse>;
  direction: 'row' | 'column';
  getLinkToResourceStatusPage: (status, resourceType) => string;
  resourceType: string;
  resources: Array<Resource>;
  total: number;
  unit: 'number' | 'percentage';
}

const Legend = ({
  data,
  total,
  unit,
  direction,
  getLinkToResourceStatusPage,
  resourceType,
  resources
}: Props): JSX.Element => {
  const { classes } = useLegendStyles({
    direction
  });

  return (
    <div className={classes.legend}>
      {data.map(({ value, color, label: status }) => {
        return (
          <div className={classes.legendItems} key={color}>
            <Tooltip
              classes={{
                tooltip: classes.tooltip
              }}
              followCursor={false}
              label={
                <TooltipContent
                  color={color}
                  label={status}
                  resourceType={resourceType}
                  resources={resources}
                  total={total}
                  value={value}
                />
              }
              position="bottom"
            >
              <Link
                rel="noopener noreferrer"
                target="_blank"
                to={getLinkToResourceStatusPage(status, resourceType)}
              >
                <div
                  className={classes.legendItem}
                  style={{ background: color }}
                />
              </Link>
            </Tooltip>
            <Typography variant="body2">
              {getValueByUnit({
                total,
                unit,
                value
              })}
            </Typography>
          </div>
        );
      })}
    </div>
  );
};

export default Legend;
