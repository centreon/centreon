import { PieArcDatum, ProvidedProps } from '@visx/shape/lib/shapes/Pie';
import { useTransition, to, animated } from '@react-spring/web';
import { equals, includes, isNil, pluck } from 'ramda';

import { Typography } from '@mui/material';

import { Thresholds } from '../common/models';

type AnimatedStyles = { endAngle: number; opacity: number; startAngle: number };

const fromLeaveTransition = ({ endAngle }): AnimatedStyles => ({
  endAngle,
  opacity: 0,
  startAngle: -(Math.PI / 2)
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
  getColor: (d: PieArcDatum<Datum>) => string;
  getKey: (d: PieArcDatum<Datum>) => string;
  hideTooltip?: () => void;
  showTooltip?: (args) => void;
  thresholds: Thresholds;
};

const AnimatedPie = <Datum,>({
  animate,
  arcs,
  path,
  getKey,
  getColor,
  showTooltip,
  hideTooltip,
  thresholds
}: AnimatedPieProps<Datum>): JSX.Element | null => {
  const transitions = useTransition?.<PieArcDatum<Datum>, AnimatedStyles>(
    arcs,
    {
      enter: enterUpdateTransition,
      from: animate ? fromLeaveTransition : enterUpdateTransition,
      keys: getKey,
      leave: animate ? fromLeaveTransition : enterUpdateTransition,
      update: enterUpdateTransition
    }
  );

  if (isNil(useTransition)) {
    return null;
  }

  return transitions((props, arc, { key }) => (
    <g key={key}>
      <animated.path
        d={to([props.startAngle, props.endAngle], (startAngle, endAngle) =>
          path({
            ...arc,
            endAngle,
            startAngle
          })
        )}
        data-testid={`${arc.data?.value || arc.data}-arc`}
        display={
          includes('transparent', arc.data?.name || '') ? 'none' : 'inline'
        }
        fill={getColor(arc)}
        onMouseEnter={(event) => {
          const thresholdType = arc.data?.name as string;

          if (equals(thresholdType, 'success')) {
            return;
          }

          showTooltip?.({
            tooltipData: (
              <div>
                {pluck(
                  'label',
                  thresholds[thresholdType as 'warning' | 'critical']
                ).map((label) => (
                  <Typography key={label} variant="body2">
                    {label}
                  </Typography>
                ))}
              </div>
            ),
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
