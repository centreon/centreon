import { Typography } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { ReactElement } from 'react';
import { Link } from 'react-router';

import { Resource } from '../../../models';
import TooltipContent from '../Tooltip/Tooltip';
import { FormattedResponse, getValueByUnit } from '../utils';
import { useLegendStyles } from './Legend.styles';

interface Props {
  data: Array<FormattedResponse>;
  direction: 'row' | 'column';
  getLinkToResourceStatusPage: (status, resourceType) => string;
  title: string;
  total: number;
  unit: 'number' | 'percentage';
  resourceType: string;
  resources: Array<Resource>;
}

const Legend = ({
  data,
  total,
  unit,
  direction,
  getLinkToResourceStatusPage,
  resourceType,
  resources
}: Props): ReactElement => {
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const { classes } = useLegendStyles({
    direction
  });

  return (
    <div className={classes.legend}>
      {data.map(({ value, color, label: status }) => {
        if (!value) {
          return null;
        }
        return (
          <div className={classes.legendItems} key={color}>
            <Tooltip
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
                  resources={resources}
                  resourceType={resourceType}
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
