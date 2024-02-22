import { Tooltip } from '@centreon/ui/components';

import TooltipContent from '../Tooltip/Tooltip';
import { FormatedResponse } from '../../utils';

interface Props {
  data: Array<FormatedResponse>;
  title: string;
  total: number;
}

const Legend =
  (classes) =>
  ({ data, title, total }: Props): JSX.Element => {
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
                  //   TooltipContent({
                  //   color,
                  //   label,
                  //   title,
                  //   total,
                  //   value
                  // })
                  'hello'
                }
                position="bottom"
              >
                <div
                  className={classes.legendItem}
                  style={{ background: color }}
                />
              </Tooltip>
              <div>{value}</div>
            </div>
          );
        })}
      </div>
    );
  };

export default Legend;
