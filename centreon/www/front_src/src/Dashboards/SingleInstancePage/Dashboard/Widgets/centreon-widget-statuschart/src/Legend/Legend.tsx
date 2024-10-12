import { useAtomValue } from 'jotai';
import { Link } from 'react-router-dom';

import { Typography } from '@mui/material';

import { isOnPublicPageAtom } from '@centreon/ui-context';
import { Tooltip } from '@centreon/ui/components';

import TooltipContent from '../Tooltip/Tooltip';
import { FormattedResponse, getValueByUnit } from '../utils';

import { useLegendStyles } from './Legend.styles';

interface Props {
  data: Array<FormattedResponse>;
  direction: 'row' | 'column';
  getLinkToResourceStatusPage: (status) => string;
  title: string;
  total: number;
  unit: 'number' | 'percentage';
}

const Legend = ({
  data,
  title,
  total,
  unit,
  direction,
  getLinkToResourceStatusPage
}: Props): JSX.Element => {
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const { classes } = useLegendStyles({
    direction
  });

  return (
    <div className={classes.legend}>
      {data.map(({ value, color, label: status }) => {
        return (
          <div className={classes.legendItems} key={color}>
            <Tooltip
              hasCaret
              classes={{
                tooltip: classes.tooltip
              }}
              disableFocusListener={isOnPublicPage}
              disableHoverListener={isOnPublicPage}
              disableTouchListener={isOnPublicPage}
              followCursor={false}
              label={
                <TooltipContent
                  color={color}
                  label={status}
                  title={title}
                  total={total}
                  value={value}
                />
              }
              position="bottom"
            >
              <Link
                rel="noopener noreferrer"
                target="_blank"
                to={getLinkToResourceStatusPage(status)}
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

export default (getLinkToResourceStatusPage) =>
  ({
    data,
    title,
    total,
    unit,
    direction
  }: Omit<Props, 'getLinkToResourceStatusPage'>) => (
    <Legend
      data={data}
      direction={direction}
      getLinkToResourceStatusPage={getLinkToResourceStatusPage}
      title={title}
      total={total}
      unit={unit}
    />
  );
