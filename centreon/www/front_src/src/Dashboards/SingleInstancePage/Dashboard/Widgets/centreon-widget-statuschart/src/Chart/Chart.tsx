import { useAtomValue } from 'jotai';
import { equals, isEmpty, isNil, reject } from 'ramda';
import { useTranslation } from 'react-i18next';

import { BarStack, PieChart } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { NoResourcesFound } from '../../../NoResourcesFound';
import {
  labelNoHostsFound,
  labelNoServicesFound
} from '../../../translatedLabels';
import { goToUrl } from '../../../utils';
import Legend from '../Legend/Legend';
import TooltipContent from '../Tooltip/Tooltip';
import { ChartType, DisplayType } from '../models';
import useLoadResources from '../useLoadResources';

import { useStyles } from './Chart.styles';
import ChartSkeleton from './LoadingSkeleton';

const Chart = ({
  displayType,
  displayLegend,
  displayValues,
  resourceType,
  unit,
  title,
  refreshCount,
  refreshIntervalToUse,
  resources,
  getLinkToResourceStatusPage,
  isHorizontalBar,
  isSingleChart,
  id,
  playlistHash,
  dashboardId,
  widgetPrefixQuery
}: ChartType): JSX.Element => {
  const { cx, classes } = useStyles();
  const { t } = useTranslation();

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const isPieCharts =
    equals(displayType, DisplayType.Pie) ||
    equals(displayType, DisplayType.Donut);

  const { data, isLoading } = useLoadResources({
    dashboardId,
    id,
    playlistHash,
    refreshCount,
    refreshIntervalToUse,
    resourceType,
    resources,
    widgetPrefixQuery
  });

  const goToResourceStatusPage = (status): void => {
    const url = getLinkToResourceStatusPage(status, resourceType);

    goToUrl(url)();
  };

  if (isLoading && isNil(data)) {
    return <ChartSkeleton />;
  }

  if (isNil(data)) {
    return <div />;
  }

  const areAllValuesNull = isEmpty(
    reject(({ value }) => equals(value, 0), data)
  );

  if (areAllValuesNull) {
    return (
      <NoResourcesFound
        label={
          equals(resourceType, 'host')
            ? t(labelNoHostsFound)
            : t(labelNoServicesFound)
        }
      />
    );
  }

  return (
    <div className={classes.container}>
      {isPieCharts ? (
        <div className={classes.pieChart}>
          <PieChart
            Legend={Legend((status) =>
              getLinkToResourceStatusPage(status, resourceType)
            )}
            TooltipContent={isOnPublicPage ? undefined : TooltipContent}
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            title={title}
            tooltipProps={{ resources }}
            unit={unit}
            variant={displayType as 'pie' | 'donut'}
            onArcClick={({ label: status }) => {
              goToResourceStatusPage(status);
            }}
          />
        </div>
      ) : (
        <div
          className={cx(classes.barStack, {
            [classes.verticalBar]: !isHorizontalBar,
            [classes.singleHorizontalBar]: isHorizontalBar && isSingleChart
          })}
        >
          <BarStack
            Legend={Legend((status) =>
              getLinkToResourceStatusPage(status, resourceType)
            )}
            TooltipContent={TooltipContent}
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            legendDirection={isHorizontalBar ? 'row' : 'column'}
            size={80}
            title={title}
            tooltipProps={{ resources }}
            unit={unit}
            variant={displayType as 'horizontal' | 'vertical'}
            onSingleBarClick={({ key: status }) => {
              goToResourceStatusPage(status);
            }}
          />
        </div>
      )}
    </div>
  );
};

export default Chart;
