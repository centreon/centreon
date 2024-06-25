import {
  Dispatch,
  MutableRefObject,
  ReactNode,
  SetStateAction,
  useMemo
} from 'react';

import { equals, gt, isNil, lte, reduce } from 'ramda';

import { Stack } from '@mui/material';

import Legend from '../../LineChart/Legend';
import { legendWidth } from '../../LineChart/Legend/Legend.styles';
import { Line } from '../timeSeries/models';

import { useBaseChartStyles } from './useBaseChartStyles';
import Header from './Header';
import { LineChartHeader } from './Header/models';

interface Props {
  base?: number;
  children: JSX.Element;
  graphWidth: number;
  header?: LineChartHeader;
  height: number | null;
  isHorizontal?: boolean;
  legend: {
    displayLegend: boolean;
    legendHeight?: number;
    mode?: 'grid' | 'list';
    placement?: 'left' | 'right' | 'bottom';
    renderExtraComponent?: ReactNode;
  };
  legendRef: MutableRefObject<HTMLDivElement | null>;
  limitLegend?: number | false;
  lines: Array<Line>;
  setLines:
    | Dispatch<SetStateAction<Array<Line> | null>>
    | Dispatch<SetStateAction<Array<Line>>>;
  title: string;
}

const BaseChart = ({
  legend,
  base = 1000,
  height,
  graphWidth,
  lines,
  limitLegend = false,
  setLines,
  children,
  legendRef,
  title,
  header,
  isHorizontal = true
}: Props): JSX.Element => {
  const { classes, cx } = useBaseChartStyles();

  const legendItemsWidth = useMemo(
    () => reduce((acc) => acc + legendWidth * 8 + 24, 0, lines),
    [lines]
  );

  const displayLegendInBottom = useMemo(
    () => isNil(legend.placement) || equals(legend.placement, 'bottom'),
    [legend.placement]
  );

  const shouldDisplayLegendInCompactMode = useMemo(
    () =>
      lte(graphWidth, 808) &&
      gt(legendItemsWidth, graphWidth) &&
      displayLegendInBottom,
    [graphWidth, displayLegendInBottom, legendItemsWidth]
  );

  return (
    <>
      <Header header={header} title={title} />
      <div className={classes.container}>
        <Stack
          direction={equals(legend?.placement, 'left') ? 'row' : 'row-reverse'}
        >
          {legend.displayLegend &&
            (equals(legend?.placement, 'left') ||
              equals(legend?.placement, 'right')) && (
              <div
                className={cx(
                  classes.legendContainer,
                  equals(legend?.placement, 'right') &&
                    !isHorizontal &&
                    classes.legendContainerVerticalSide
                )}
                ref={legendRef}
              >
                <Legend
                  base={base}
                  height={height}
                  limitLegend={limitLegend}
                  lines={lines}
                  mode={legend?.mode}
                  placement="left"
                  renderExtraComponent={legend?.renderExtraComponent}
                  setLinesGraph={setLines}
                  shouldDisplayLegendInCompactMode={
                    shouldDisplayLegendInCompactMode
                  }
                />
              </div>
            )}
          {children}
        </Stack>
      </div>
      {legend.displayLegend && displayLegendInBottom && (
        <div
          ref={legendRef}
          style={{ height: legend?.legendHeight ?? 'undefined' }}
        >
          <Legend
            base={base}
            height={height}
            limitLegend={limitLegend}
            lines={lines}
            mode={legend.mode}
            placement="bottom"
            renderExtraComponent={legend.renderExtraComponent}
            setLinesGraph={setLines}
            shouldDisplayLegendInCompactMode={shouldDisplayLegendInCompactMode}
          />
        </div>
      )}
    </>
  );
};

export default BaseChart;
