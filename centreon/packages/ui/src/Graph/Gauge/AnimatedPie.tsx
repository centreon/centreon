import { PieArcDatum, ProvidedProps } from '@visx/shape/lib/shapes/Pie';
import { useTransition, interpolate, animated } from '@react-spring/web';
import { equals } from 'ramda';

import { Metric } from '../common/timeSeries/models';

import { ThresholdType } from './models';

type AnimatedStyles = { endAngle: number; opacity: number; startAngle: number };

const fromLeaveTransition = (): AnimatedStyles => ({
  endAngle: 0,
  opacity: 0,
  startAngle: 0
});
const enterUpdateTransition = <T,>({
  startAngle,
  endAngle
}: PieArcDatum<T>): AnimatedStyles => ({
  endAngle,
  opacity: 1,
  startAngle
});

type AnimatedPieProps<Datum> = ProvidedProps<Datum> & {
  animate?: boolean;
  delay?: number;
  getColor: (d: PieArcDatum<Datum>) => string;
  getKey: (d: PieArcDatum<Datum>) => string;
  hideTooltip?: () => void;
  metric?: Metric;
  showTooltip?: (args) => void;
  thresholdTooltipLabels?: Array<string>;
};

const AnimatedPie = <Datum,>({
  animate,
  arcs,
  path,
  getKey,
  getColor,
  showTooltip,
  hideTooltip,
  thresholdTooltipLabels = []
}: AnimatedPieProps<Datum>): JSX.Element => {
  const transitions = useTransition<PieArcDatum<Datum>, AnimatedStyles>(arcs, {
    enter: enterUpdateTransition,
    from: animate ? fromLeaveTransition : enterUpdateTransition,
    keys: getKey,
    leave: animate ? fromLeaveTransition : enterUpdateTransition,
    update: enterUpdateTransition
  });

  return transitions((props, arc, { key }) => (
    <g key={key}>
      <animated.path
        d={interpolate(
          [props.startAngle, props.endAngle],
          (startAngle, endAngle) =>
            path({
              ...arc,
              endAngle,
              startAngle
            })
        )}
        data-testid={`${arc.data?.value || arc.data}-arc`}
        fill={getColor(arc)}
        onMouseEnter={(event) => {
          const thresholdType = arc.data.name;

          if (equals(thresholdType, ThresholdType.Success)) {
            return;
          }

          showTooltip?.({
            tooltipData: thresholdTooltipLabels[thresholdType],
            tooltipLeft: event.clientX,
            tooltipTop: event.clientY
          });
        }}
        onMouseLeave={() => hideTooltip?.()}
      />
    </g>
  ));
};

export default AnimatedPie;
