import { Typography } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import TooltipContent from '../Tooltip/Tooltip';
import { FormattedResponse, getValueByUnit } from '../../utils';

import { useLegendStyles } from './Legend.styles';

interface Props {
  data: Array<FormattedResponse>;
  direction: 'row' | 'column';
  title: string;
  total: number;
  unit: 'number' | 'percentage';
}

const Legend = ({
  data,
  title,
  total,
  unit,
  direction
}: Props): JSX.Element => {
  const { classes } = useLegendStyles({
    direction
  });

  return (
    <div className={classes.legend}>
      {data.map(({ value, color, label }) => {
        return (
          <div className={classes.legendItems} key={color}>
            <Tooltip
              hasCaret
              classes={{
                tooltip: classes.tooltip
              }}
              followCursor={false}
              label={
                <TooltipContent
                  color={color}
                  label={label}
                  title={title}
                  total={total}
                  value={value}
                />
              }
              position="bottom"
            >
              <div
                className={classes.legendItem}
                style={{ background: color }}
              />
            </Tooltip>
            <Typography>
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
