// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { BarStack, PieChart, type PieProps } from '@centreon/ui';
import { isOnPublicPageAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { equals, isEmpty, isNil, reject } from 'ramda';
import { type ComponentProps, type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

import { NoResourcesFound } from '../../../NoResourcesFound';
import {
  labelNoHostsFound,
  labelNoServicesFound
} from '../../../translatedLabels';
import { goToUrl } from '../../../utils';
import Legend from '../Legend/Legend';
import { type ChartType, DisplayType } from '../models';
import TooltipContent from '../Tooltip/Tooltip';
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
  isInViewport,
  widgetPrefixQuery,
  stateList
}: ChartType): ReactElement => {
  const { cx, classes } = useStyles();
  const { t } = useTranslation();

  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  const isPieCharts =
    equals(displayType, DisplayType.Pie) ||
    equals(displayType, DisplayType.Donut);

  const { data, isLoading } = useLoadResources({
    dashboardId,
    id,
    isInViewport,
    playlistHash,
    refreshCount,
    refreshIntervalToUse,
    resources,
    resourceType,
    stateList,
    widgetPrefixQuery
  });

  const goToResourceStatusPage = (status: string): void => {
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
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            Legend={(props) => (
              <Legend
                getLinkToResourceStatusPage={getLinkToResourceStatusPage}
                resources={resources}
                resourceType={resourceType}
                {...props}
              />
            )}
            onArcClick={({ label: status }) => {
              goToResourceStatusPage(status);
            }}
            opacity={1}
            TooltipContent={
              (isOnPublicPage
                ? undefined
                : TooltipContent) as PieProps['TooltipContent']
            }
            title={title}
            tooltipProps={{ resources, resourceType }}
            unit={unit}
            variant={displayType as 'pie' | 'donut'}
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
            data={data}
            displayLegend={displayLegend}
            displayValues={displayValues}
            Legend={(props) => (
              <Legend
                getLinkToResourceStatusPage={getLinkToResourceStatusPage}
                resources={resources}
                resourceType={resourceType}
                {...props}
              />
            )}
            legendDirection={isHorizontalBar ? 'row' : 'column'}
            onSingleBarClick={({ key: status }: { key: string }) => {
              goToResourceStatusPage(status);
            }}
            TooltipContent={
              TooltipContent as ComponentProps<
                typeof BarStack
              >['TooltipContent']
            }
            title={title}
            tooltipProps={{ resources, resourceType }}
            unit={unit}
            variant={displayType as 'horizontal' | 'vertical'}
          />
        </div>
      )}
    </div>
  );
};

export default Chart;
