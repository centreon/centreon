<<<<<<< HEAD
import { memo, MouseEvent, useEffect, useMemo, useRef, useState } from 'react';
=======
import * as React from 'react';
>>>>>>> centreon/dev-21.10.x

import { equals, isNil, identity, min, max, not, lt, gte } from 'ramda';
import {
  Shape,
  Grid,
  Scale,
  Group,
  Tooltip as VisxTooltip,
  Event,
} from '@visx/visx';
import { bisector } from 'd3-array';
import { ScaleLinear } from 'd3-scale';
import { useTranslation } from 'react-i18next';
<<<<<<< HEAD
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
=======
>>>>>>> centreon/dev-21.10.x

import {
  Button,
  ClickAwayListener,
<<<<<<< HEAD
=======
  makeStyles,
>>>>>>> centreon/dev-21.10.x
  Paper,
  Typography,
  Theme,
  alpha,
  useTheme,
  CircularProgress,
  Tooltip,
<<<<<<< HEAD
} from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import { grey } from '@mui/material/colors';
=======
} from '@material-ui/core';
import { grey } from '@material-ui/core/colors';
>>>>>>> centreon/dev-21.10.x

import {
  dateTimeFormat,
  useLocaleDateTimeFormat,
  useMemoComponent,
} from '@centreon/ui';

import { TimeValue, Line as LineModel, AdjustTimePeriodProps } from '../models';
import {
  getTime,
  getMin,
  getMax,
  getDates,
  getUnits,
  getMetricValuesForUnit,
  getMetricValuesForLines,
  getSortedStackedLines,
  getStackedMetricValues,
  hasUnitStackedLines,
<<<<<<< HEAD
=======
  getMetrics,
  getLineForMetric,
>>>>>>> centreon/dev-21.10.x
} from '../timeSeries';
import Lines from '../Lines';
import {
  labelActionNotPermitted,
  labelAddComment,
} from '../../../translatedLabels';
import { TimelineEvent } from '../../../Details/tabs/Timeline/models';
import { Resource } from '../../../models';
import { ResourceDetails } from '../../../Details/models';
import { CommentParameters } from '../../../Actions/api';
import useAclQuery from '../../../Actions/Resource/aclQuery';
import memoizeComponent from '../../../memoizedComponent';
<<<<<<< HEAD
=======
import { ResourceGraphMousePosition } from '../../../Details/tabs/Services/Graphs';
>>>>>>> centreon/dev-21.10.x

import AddCommentForm from './AddCommentForm';
import Annotations from './Annotations';
import Axes from './Axes';
<<<<<<< HEAD
=======
import { AnnotationsContext } from './Context';
import useAnnotations from './useAnnotations';
>>>>>>> centreon/dev-21.10.x
import TimeShiftZones, {
  TimeShiftContext,
  TimeShiftDirection,
} from './TimeShiftZones';
<<<<<<< HEAD
import {
  changeMousePositionAndTimeValueDerivedAtom,
  changeTimeValueDerivedAtom,
  MousePosition,
  mousePositionAtom,
} from './mouseTimeValueAtoms';
import {
  annotationHoveredAtom,
  changeAnnotationHoveredDerivedAtom,
} from './annotationsAtoms';
=======
import { MousePosition, useMetricsValueContext } from './useMetricsValue';
>>>>>>> centreon/dev-21.10.x

const propsAreEqual = (prevProps, nextProps): boolean =>
  equals(prevProps, nextProps);

<<<<<<< HEAD
const MemoizedAxes = memo(Axes, propsAreEqual);
const MemoizedBar = memo(Shape.Bar, propsAreEqual);
const MemoizedGridColumns = memo(Grid.GridColumns, propsAreEqual);
const MemoizedGridRows = memo(Grid.GridRows, propsAreEqual);
const MemoizedLines = memo(Lines, propsAreEqual);
const MemoizedAnnotations = memo(Annotations, propsAreEqual);
=======
const MemoizedAxes = React.memo(Axes, propsAreEqual);
const MemoizedBar = React.memo(Shape.Bar, propsAreEqual);
const MemoizedGridColumns = React.memo(Grid.GridColumns, propsAreEqual);
const MemoizedGridRows = React.memo(Grid.GridRows, propsAreEqual);
const MemoizedLines = React.memo(Lines, propsAreEqual);
const MemoizedAnnotations = React.memo(Annotations, propsAreEqual);
>>>>>>> centreon/dev-21.10.x

const margin = { bottom: 30, left: 45, right: 45, top: 30 };

const commentTooltipWidth = 165;

interface Props {
  base: number;
  height: number;
  lines: Array<LineModel>;
  onAddComment?: (commentParameters: CommentParameters) => void;
  resource: Resource | ResourceDetails;
  timeSeries: Array<TimeValue>;
  timeline?: Array<TimelineEvent>;
  width: number;
  xAxisTickFormat: string;
}

const useStyles = makeStyles<Theme, Pick<Props, 'onAddComment'>>((theme) => ({
  addCommentButton: {
    fontSize: 10,
  },
  addCommentTooltip: {
    display: 'grid',
    fontSize: 10,
    gridAutoFlow: 'row',
    justifyItems: 'center',
    padding: theme.spacing(0.5),
    position: 'absolute',
  },
  container: {
<<<<<<< HEAD
    '& .visx-axis-bottom': {
      '& .visx-axis-tick': {
        '& .visx-line': {
          stroke: theme.palette.text.primary,
        },
      },
    },
    '& .visx-axis-line': {
      stroke: theme.palette.text.primary,
    },
    '& .visx-axis-right': {
      '& .visx-axis-tick': {
        '& .visx-line': {
          stroke: theme.palette.text.primary,
        },
      },
    },
    '& .visx-columns': {
      '& .visx-line': {
        stroke: theme.palette.divider,
      },
    },
    '& .visx-rows': {
      '& .visx-line': {
        stroke: theme.palette.divider,
      },
    },
    fill: theme.palette.text.primary,
=======
>>>>>>> centreon/dev-21.10.x
    position: 'relative',
  },
  graphLoader: {
    alignItems: 'center',
    backgroundColor: alpha(theme.palette.common.white, 0.5),
    display: 'flex',
    height: '100%',
    justifyContent: 'center',
    position: 'absolute',
    width: '100%',
  },
  overlay: {
    cursor: ({ onAddComment }): string =>
      isNil(onAddComment) ? 'normal' : 'crosshair',
  },
  tooltip: {
    padding: 12,
    zIndex: theme.zIndex.tooltip,
  },
}));

interface ZoomBoundaries {
  end: number;
  start: number;
}

interface GraphContentProps {
  addCommentTooltipLeft?: number;
  addCommentTooltipOpen: boolean;
  addCommentTooltipTop?: number;
  applyZoom?: (props: AdjustTimePeriodProps) => void;
  base: number;
  canAdjustTimePeriod: boolean;
<<<<<<< HEAD
  containsMetrics: boolean;
  displayEventAnnotations: boolean;
  displayTimeValues: boolean;
  format: (parameters) => string;
  height: number;
  hideAddCommentTooltip: () => void;
  isInViewport?: boolean;
=======
  changeMetricsValue: (props) => void;
  containsMetrics: boolean;
  displayEventAnnotations: boolean;
  format: (parameters) => string;
  height: number;
  hideAddCommentTooltip: () => void;
>>>>>>> centreon/dev-21.10.x
  lines: Array<LineModel>;
  loading: boolean;
  onAddComment?: (commentParameters: CommentParameters) => void;
  resource: Resource | ResourceDetails;
<<<<<<< HEAD
=======
  resourceGraphMousePosition?: ResourceGraphMousePosition | null;
>>>>>>> centreon/dev-21.10.x
  shiftTime?: (direction: TimeShiftDirection) => void;
  showAddCommentTooltip: (args) => void;
  timeSeries: Array<TimeValue>;
  timeline?: Array<TimelineEvent>;
  width: number;
  xAxisTickFormat: string;
}

const getScale = ({
  values,
  height,
  stackedValues,
}): ScaleLinear<number, number> => {
  const minValue = min(getMin(values), getMin(stackedValues));
  const maxValue = max(getMax(values), getMax(stackedValues));

  const upperRangeValue = minValue === maxValue && maxValue === 0 ? height : 0;

  return Scale.scaleLinear<number>({
    domain: [minValue, maxValue],
    nice: true,
    range: [height, upperRangeValue],
  });
};

export const bisectDate = bisector(identity).center;

const GraphContent = ({
  width,
  height,
  timeSeries,
  base,
  lines,
  xAxisTickFormat,
  timeline,
  resource,
  addCommentTooltipLeft,
  addCommentTooltipTop,
  addCommentTooltipOpen,
  onAddComment,
  hideAddCommentTooltip,
  showAddCommentTooltip,
  format,
  applyZoom,
  shiftTime,
  loading,
  canAdjustTimePeriod,
  displayEventAnnotations,
  containsMetrics,
<<<<<<< HEAD
  isInViewport,
  displayTimeValues,
=======
  changeMetricsValue,
  resourceGraphMousePosition,
>>>>>>> centreon/dev-21.10.x
}: GraphContentProps): JSX.Element => {
  const classes = useStyles({ onAddComment });
  const { t } = useTranslation();

<<<<<<< HEAD
  const [addingComment, setAddingComment] = useState(false);
  const [commentDate, setCommentDate] = useState<Date>();
  const [zoomPivotPosition, setZoomPivotPosition] = useState<number | null>(
    null,
  );
  const [zoomBoundaries, setZoomBoundaries] = useState<ZoomBoundaries | null>(
    null,
  );
  const graphSvgRef = useRef<SVGSVGElement | null>(null);
  const { canComment } = useAclQuery();
  const mousePosition = useAtomValue(mousePositionAtom);
  const changeMousePositionAndTimeValue = useUpdateAtom(
    changeMousePositionAndTimeValueDerivedAtom,
  );
  const changeTimeValue = useUpdateAtom(changeTimeValueDerivedAtom);
  const setAnnotationHovered = useUpdateAtom(annotationHoveredAtom);
  const changeAnnotationHovered = useUpdateAtom(
    changeAnnotationHoveredDerivedAtom,
  );
=======
  const [addingComment, setAddingComment] = React.useState(false);
  const [commentDate, setCommentDate] = React.useState<Date>();
  const [zoomPivotPosition, setZoomPivotPosition] = React.useState<
    number | null
  >(null);
  const [zoomBoundaries, setZoomBoundaries] =
    React.useState<ZoomBoundaries | null>(null);
  const graphSvgRef = React.useRef<SVGSVGElement | null>(null);
  const { canComment } = useAclQuery();
>>>>>>> centreon/dev-21.10.x

  const theme = useTheme();

  const graphWidth = width > 0 ? width - margin.left - margin.right : 0;
  const graphHeight = height > 0 ? height - margin.top - margin.bottom : 0;

<<<<<<< HEAD
  const hideAddCommentTooltipOnEspcapePress = (
    event: globalThis.KeyboardEvent,
  ): void => {
=======
  const annotations = useAnnotations(graphWidth);

  const { changeMousePositionAndMetricsValue, mousePosition } =
    useMetricsValueContext();

  const hideAddCommentTooltipOnEspcapePress = (event: KeyboardEvent): void => {
>>>>>>> centreon/dev-21.10.x
    if (event.key === 'Escape') {
      hideAddCommentTooltip();
    }
  };

<<<<<<< HEAD
  useEffect(() => {
=======
  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    document.addEventListener(
      'keydown',
      hideAddCommentTooltipOnEspcapePress,
      false,
    );

    return (): void => {
      document.removeEventListener(
        'keydown',
        hideAddCommentTooltipOnEspcapePress,
        false,
      );
    };
  }, []);

<<<<<<< HEAD
  const xScale = useMemo(
=======
  const xScale = React.useMemo(
>>>>>>> centreon/dev-21.10.x
    () =>
      Scale.scaleTime<number>({
        domain: [
          getMin(timeSeries.map(getTime)),
          getMax(timeSeries.map(getTime)),
        ],
        range: [0, graphWidth],
      }),
    [graphWidth, timeSeries],
  );

  const [firstUnit, secondUnit, thirdUnit] = getUnits(lines);

<<<<<<< HEAD
  const leftScale = useMemo(() => {
=======
  const leftScale = React.useMemo(() => {
>>>>>>> centreon/dev-21.10.x
    const values = isNil(thirdUnit)
      ? getMetricValuesForUnit({ lines, timeSeries, unit: firstUnit })
      : getMetricValuesForLines({ lines, timeSeries });

    const firstUnitHasStackedLines =
      isNil(thirdUnit) && not(isNil(firstUnit))
        ? hasUnitStackedLines({ lines, unit: firstUnit })
        : false;

    const stackedValues = firstUnitHasStackedLines
      ? getStackedMetricValues({
          lines: getSortedStackedLines(lines),
          timeSeries,
        })
      : [0];

    return getScale({ height: graphHeight, stackedValues, values });
  }, [timeSeries, lines, firstUnit, graphHeight]);

<<<<<<< HEAD
  const rightScale = useMemo(() => {
=======
  const rightScale = React.useMemo(() => {
>>>>>>> centreon/dev-21.10.x
    const values = getMetricValuesForUnit({
      lines,
      timeSeries,
      unit: secondUnit,
    });

    const secondUnitHasStackedLines = isNil(secondUnit)
      ? false
      : hasUnitStackedLines({ lines, unit: secondUnit });

    const stackedValues = secondUnitHasStackedLines
      ? getStackedMetricValues({
          lines: getSortedStackedLines(lines),
          timeSeries,
        })
      : [0];

    return getScale({ height: graphHeight, stackedValues, values });
  }, [timeSeries, lines, secondUnit, graphHeight]);

  const getTimeValue = (x: number): TimeValue => {
    const date = xScale.invert(x - margin.left);
    const index = bisectDate(getDates(timeSeries), date);

    return timeSeries[index];
  };

  const updateMousePosition = (position: MousePosition): void => {
    if (isNil(position)) {
<<<<<<< HEAD
      changeMousePositionAndTimeValue({
=======
      changeMousePositionAndMetricsValue({
        base,
        lines,
>>>>>>> centreon/dev-21.10.x
        position: null,
        timeValue: null,
      });

      return;
    }
    const timeValue = getTimeValue(position[0]);

<<<<<<< HEAD
    changeMousePositionAndTimeValue({ position, timeValue });
  };

  const displayTooltip = (event: MouseEvent<SVGRectElement>): void => {
=======
    changeMousePositionAndMetricsValue({ base, lines, position, timeValue });
  };

  const displayTooltip = (event: React.MouseEvent): void => {
>>>>>>> centreon/dev-21.10.x
    const { x, y } = Event.localPoint(
      graphSvgRef.current as SVGSVGElement,
      event,
    ) || { x: 0, y: 0 };

    const mouseX = x - margin.left;

<<<<<<< HEAD
    changeAnnotationHovered({
      graphWidth,
      mouseX,
      resourceId: resource.uuid,
=======
    annotations.changeAnnotationHovered({
      mouseX,
>>>>>>> centreon/dev-21.10.x
      timeline,
      xScale,
    });

    if (zoomPivotPosition) {
      setZoomBoundaries({
        end: gte(mouseX, zoomPivotPosition) ? mouseX : zoomPivotPosition,
        start: lt(mouseX, zoomPivotPosition) ? mouseX : zoomPivotPosition,
      });
<<<<<<< HEAD
      changeTimeValue({ isInViewport, newTimeValue: null });
=======
      changeMetricsValue({ newMetricsValue: null });
>>>>>>> centreon/dev-21.10.x

      return;
    }

    const position: MousePosition = [x, y];

    updateMousePosition(position);
  };

  const closeZoomPreview = (): void => {
    setZoomBoundaries(null);
    setZoomPivotPosition(null);
  };

  const closeTooltip = (): void => {
    updateMousePosition(null);
<<<<<<< HEAD
    setAnnotationHovered(undefined);
=======
    annotations.setAnnotationHovered(undefined);
>>>>>>> centreon/dev-21.10.x

    if (not(isNil(zoomPivotPosition))) {
      return;
    }
    closeZoomPreview();
  };

  const displayAddCommentTooltip = (event): void => {
    setZoomBoundaries(null);
    setZoomPivotPosition(null);
    if (isNil(onAddComment)) {
      return;
    }

    if (zoomBoundaries?.start !== zoomBoundaries?.end) {
      applyZoom?.({
        end: xScale.invert(zoomBoundaries?.end || graphWidth),
        start: xScale.invert(zoomBoundaries?.start || 0),
      });

      return;
    }

    const { x, y } = Event.localPoint(event) || { x: 0, y: 0 };

    const { timeTick } = getTimeValue(x);
    const date = new Date(timeTick);

    setCommentDate(date);

    const displayLeft = width - x < commentTooltipWidth;

    showAddCommentTooltip({
      tooltipLeft: displayLeft ? x - commentTooltipWidth : x,
      tooltipTop: y,
    });
  };

  const prepareAddComment = (): void => {
    setAddingComment(true);
    hideAddCommentTooltip();
  };

  const confirmAddComment = (comment): void => {
    setAddingComment(false);
    onAddComment?.(comment);
  };

  const displayZoomPreview = (event): void => {
    if (isNil(onAddComment)) {
      return;
    }
    const { x } = Event.localPoint(event) || { x: 0 };

    const mouseX = x - margin.left;

    setZoomPivotPosition(mouseX);
    setZoomBoundaries({
      end: mouseX,
      start: mouseX,
    });
    hideAddCommentTooltip();
  };

<<<<<<< HEAD
  const position = mousePosition;
=======
  React.useEffect((): void => {
    if (isNil(resourceGraphMousePosition)) {
      changeMetricsValue({
        newMetricsValue: null,
      });

      return;
    }
    const { resourceId, mousePosition: mousePositionContext } =
      resourceGraphMousePosition;
    if (
      equals(resourceId, resource.id) ||
      equals(mousePositionContext, mousePosition) ||
      isNil(mousePositionContext)
    ) {
      return;
    }

    const timeValue = getTimeValue(mousePositionContext[0]);

    const metrics = getMetrics(timeValue);

    const metricsToDisplay = metrics.filter((metric) => {
      const line = getLineForMetric({ lines, metric });

      return !isNil(timeValue[metric]) && !isNil(line);
    });

    changeMetricsValue({
      newMetricsValue: {
        base,
        lines,
        metrics: metricsToDisplay,
        timeValue,
      },
    });
  }, [resourceGraphMousePosition]);

  const position = mousePosition || resourceGraphMousePosition?.mousePosition;
>>>>>>> centreon/dev-21.10.x

  const mousePositionX = (position?.[0] || 0) - margin.left;
  const mousePositionY = (position?.[1] || 0) - margin.top;

  const zoomBarWidth = Math.abs(
    (zoomBoundaries?.end || 0) - (zoomBoundaries?.start || 0),
  );

  const mousePositionTimeTick = position
    ? getTimeValue(position[0]).timeTick
    : 0;

  const timeTick = containsMetrics ? new Date(mousePositionTimeTick) : null;

  const isCommentPermitted = canComment([resource]);

  const commentTitle = isCommentPermitted ? '' : t(labelActionNotPermitted);

  return (
<<<<<<< HEAD
    <ClickAwayListener onClickAway={hideAddCommentTooltip}>
      <div className={classes.container}>
        {loading && (
          <div className={classes.graphLoader}>
            <CircularProgress />
          </div>
        )}
        <svg
          height={height}
          ref={graphSvgRef}
          width="100%"
          onMouseUp={closeZoomPreview}
        >
          <Group.Group left={margin.left} top={margin.top}>
            <MemoizedGridRows
              height={graphHeight}
              scale={rightScale || leftScale}
              width={graphWidth}
            />
            <MemoizedGridColumns
              height={graphHeight}
              scale={xScale}
              width={graphWidth}
            />
            <MemoizedAxes
              base={base}
              graphHeight={graphHeight}
              graphWidth={graphWidth}
              leftScale={leftScale}
              lines={lines}
              rightScale={rightScale}
              xAxisTickFormat={xAxisTickFormat}
              xScale={xScale}
            />
            <MemoizedLines
              displayTimeValues={displayTimeValues}
              graphHeight={graphHeight}
              leftScale={leftScale}
              lines={lines}
              rightScale={rightScale}
              timeSeries={timeSeries}
              timeTick={timeTick}
              xScale={xScale}
            />
            {displayEventAnnotations && (
              <MemoizedAnnotations
                graphHeight={graphHeight}
                resourceId={resource.uuid}
                timeline={timeline as Array<TimelineEvent>}
                xScale={xScale}
              />
            )}
            <MemoizedBar
              fill={alpha(theme.palette.primary.main, 0.2)}
              height={graphHeight}
              stroke={alpha(theme.palette.primary.main, 0.5)}
              width={zoomBarWidth}
              x={zoomBoundaries?.start || 0}
              y={0}
            />
            {useMemoComponent({
              Component:
                displayTimeValues && containsMetrics && position ? (
                  <g>
                    <Shape.Line
                      from={{ x: mousePositionX, y: 0 }}
                      pointerEvents="none"
                      stroke={grey[400]}
                      strokeWidth={1}
                      to={{ x: mousePositionX, y: graphHeight }}
                    />
                    <Shape.Line
                      from={{ x: 0, y: mousePositionY }}
                      pointerEvents="none"
                      stroke={grey[400]}
                      strokeWidth={1}
                      to={{ x: graphWidth, y: mousePositionY }}
                    />
                  </g>
                ) : (
                  <g />
                ),
              memoProps: [mousePosition],
            })}
            <MemoizedBar
              className={classes.overlay}
              fill="transparent"
              height={graphHeight}
              width={graphWidth}
              x={0}
              y={0}
              onMouseDown={displayZoomPreview}
              onMouseLeave={closeTooltip}
              onMouseMove={displayTooltip}
              onMouseUp={displayAddCommentTooltip}
            />
          </Group.Group>
          <TimeShiftContext.Provider
            value={useMemo(
              () => ({
=======
    <AnnotationsContext.Provider value={annotations}>
      <ClickAwayListener onClickAway={hideAddCommentTooltip}>
        <div className={classes.container}>
          {loading && (
            <div className={classes.graphLoader}>
              <CircularProgress />
            </div>
          )}
          <svg
            height={height}
            ref={graphSvgRef}
            width="100%"
            onMouseUp={closeZoomPreview}
          >
            <Group.Group left={margin.left} top={margin.top}>
              <MemoizedGridRows
                height={graphHeight}
                scale={rightScale || leftScale}
                stroke={grey[100]}
                width={graphWidth}
              />
              <MemoizedGridColumns
                height={graphHeight}
                scale={xScale}
                stroke={grey[100]}
                width={graphWidth}
              />
              <MemoizedAxes
                base={base}
                graphHeight={graphHeight}
                graphWidth={graphWidth}
                leftScale={leftScale}
                lines={lines}
                rightScale={rightScale}
                xAxisTickFormat={xAxisTickFormat}
                xScale={xScale}
              />
              <MemoizedLines
                graphHeight={graphHeight}
                leftScale={leftScale}
                lines={lines}
                rightScale={rightScale}
                timeSeries={timeSeries}
                timeTick={timeTick}
                xScale={xScale}
              />
              {displayEventAnnotations && (
                <MemoizedAnnotations
                  graphHeight={graphHeight}
                  timeline={timeline as Array<TimelineEvent>}
                  xScale={xScale}
                />
              )}
              <MemoizedBar
                fill={alpha(theme.palette.primary.main, 0.2)}
                height={graphHeight}
                stroke={alpha(theme.palette.primary.main, 0.5)}
                width={zoomBarWidth}
                x={zoomBoundaries?.start || 0}
                y={0}
              />
              {useMemoComponent({
                Component:
                  containsMetrics && position ? (
                    <>
                      <Shape.Line
                        from={{ x: mousePositionX, y: 0 }}
                        pointerEvents="none"
                        stroke={grey[400]}
                        strokeWidth={1}
                        to={{ x: mousePositionX, y: graphHeight }}
                      />
                      <Shape.Line
                        from={{ x: 0, y: mousePositionY }}
                        pointerEvents="none"
                        stroke={grey[400]}
                        strokeWidth={1}
                        to={{ x: graphWidth, y: mousePositionY }}
                      />
                    </>
                  ) : (
                    <></>
                  ),
                memoProps: [
                  isNil(resourceGraphMousePosition) ||
                  equals(resource.id, resourceGraphMousePosition?.resourceId)
                    ? mousePosition
                    : resourceGraphMousePosition,
                ],
              })}
              <MemoizedBar
                className={classes.overlay}
                fill="transparent"
                height={graphHeight}
                width={graphWidth}
                x={0}
                y={0}
                onMouseDown={displayZoomPreview}
                onMouseLeave={closeTooltip}
                onMouseMove={displayTooltip}
                onMouseUp={displayAddCommentTooltip}
              />
            </Group.Group>
            <TimeShiftContext.Provider
              value={{
>>>>>>> centreon/dev-21.10.x
                canAdjustTimePeriod,
                graphHeight,
                graphWidth,
                loading,
                marginLeft: margin.left,
                marginTop: margin.top,
                shiftTime,
<<<<<<< HEAD
              }),
              [
                canAdjustTimePeriod,
                graphHeight,
                graphWidth,
                loading,
                margin,
                shiftTime,
              ],
            )}
          >
            <TimeShiftZones />
          </TimeShiftContext.Provider>
        </svg>
        {addCommentTooltipOpen && (
          <Paper
            className={classes.addCommentTooltip}
            style={{
              left: addCommentTooltipLeft,
              top: addCommentTooltipTop,
              width: commentTooltipWidth,
            }}
          >
            <Typography variant="caption">
              {format({
                date: new Date(commentDate as Date),
                formatString: dateTimeFormat,
              })}
            </Typography>
            <Tooltip title={commentTitle}>
              <div>
                <Button
                  className={classes.addCommentButton}
                  color="primary"
                  disabled={!isCommentPermitted}
                  size="small"
                  onClick={prepareAddComment}
                >
                  {t(labelAddComment)}
                </Button>
              </div>
            </Tooltip>
          </Paper>
        )}
        {addingComment && (
          <AddCommentForm
            date={commentDate as Date}
            resource={resource}
            onClose={(): void => {
              setAddingComment(false);
            }}
            onSuccess={confirmAddComment}
          />
        )}
      </div>
    </ClickAwayListener>
=======
              }}
            >
              <TimeShiftZones />
            </TimeShiftContext.Provider>
          </svg>
          {addCommentTooltipOpen && (
            <Paper
              className={classes.addCommentTooltip}
              style={{
                left: addCommentTooltipLeft,
                top: addCommentTooltipTop,
                width: commentTooltipWidth,
              }}
            >
              <Typography variant="caption">
                {format({
                  date: new Date(commentDate as Date),
                  formatString: dateTimeFormat,
                })}
              </Typography>
              <Tooltip title={commentTitle}>
                <div>
                  <Button
                    className={classes.addCommentButton}
                    color="primary"
                    disabled={!isCommentPermitted}
                    size="small"
                    onClick={prepareAddComment}
                  >
                    {t(labelAddComment)}
                  </Button>
                </div>
              </Tooltip>
            </Paper>
          )}
          {addingComment && (
            <AddCommentForm
              date={commentDate as Date}
              resource={resource}
              onClose={(): void => {
                setAddingComment(false);
              }}
              onSuccess={confirmAddComment}
            />
          )}
        </div>
      </ClickAwayListener>
    </AnnotationsContext.Provider>
>>>>>>> centreon/dev-21.10.x
  );
};

const memoProps = [
  'addCommentTooltipLeft',
  'addCommentTooltipTop',
  'addCommentTooltipOpen',
  'width',
  'height',
  'timeSeries',
  'base',
  'lines',
  'xAxisTickFormat',
  'timeline',
  'resource',
  'loading',
  'canAdjustTimePeriod',
  'displayTooltipValues',
  'displayEventAnnotations',
  'containsMetrics',
  'isInViewport',
<<<<<<< HEAD
=======
  'resourceGraphMousePosition',
>>>>>>> centreon/dev-21.10.x
];

const MemoizedGraphContent = memoizeComponent<GraphContentProps>({
  Component: GraphContent,
  memoProps,
});

const Graph = (
  props: Omit<
    GraphContentProps,
    | 'addCommentTooltipLeft'
    | 'addCommentTooltipTop'
    | 'addCommentTooltipOpen'
    | 'showAddCommentTooltip'
    | 'hideAddCommentTooltip'
    | 'format'
    | 'changeMetricsValue'
    | 'isInViewport'
  >,
): JSX.Element => {
  const { format } = useLocaleDateTimeFormat();
  const {
    tooltipLeft: addCommentTooltipLeft,
    tooltipTop: addCommentTooltipTop,
    tooltipOpen: addCommentTooltipOpen,
    showTooltip: showAddCommentTooltip,
    hideTooltip: hideAddCommentTooltip,
  } = VisxTooltip.useTooltip();
<<<<<<< HEAD
=======
  const { changeMetricsValue } = useMetricsValueContext();
>>>>>>> centreon/dev-21.10.x

  return (
    <MemoizedGraphContent
      {...props}
      addCommentTooltipLeft={addCommentTooltipLeft}
      addCommentTooltipOpen={addCommentTooltipOpen}
      addCommentTooltipTop={addCommentTooltipTop}
<<<<<<< HEAD
=======
      changeMetricsValue={changeMetricsValue}
>>>>>>> centreon/dev-21.10.x
      format={format}
      hideAddCommentTooltip={hideAddCommentTooltip}
      showAddCommentTooltip={showAddCommentTooltip}
    />
  );
};

export default Graph;
