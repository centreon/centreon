import { BarStackHorizontal } from '@visx/shape';
import { Group } from '@visx/group';
import { scaleBand, scaleLinear, scaleOrdinal } from '@visx/scale';
import { withTooltip, Tooltip, defaultStyles } from '@visx/tooltip';
import { LegendOrdinal } from '@visx/legend';

import { BarStackProps } from './models';
import { useBarStackStyles } from './BarStack.styles';

const defaultMargin = { bottom: 0, left: 0, right: 0, top: 0 };

const tooltipStyles = {
  ...defaultStyles,
  backgroundColor: 'rgba(0,0,0,0.9)',
  color: 'white',
  minHeight: 100,
  minWidth: 100
};

const data = [{ Down: 22, Ok: 121, Unknown: 19, Warning: 13 }];

const keys = Object.keys(data[0]);

const total = Object.values(data[0]).reduce((acc, curr) => acc + curr, 0);

// scales
const xScale = scaleLinear({
  domain: [0, total],
  nice: true
});
const yScale = scaleBand({
  domain: [0, 0],
  padding: 0
});

const colorScale = scaleOrdinal({
  domain: keys,
  range: ['#88B922', '#999999', '#F7931A', '#FF6666']
});

const legendScale = scaleOrdinal({
  domain: Object.values(data[0]),
  range: ['#88B922', '#999999', '#F7931A', '#FF6666']
});

let tooltipTimeout;

const ResponsiveBarStack = ({
  width,
  height,
  margin = defaultMargin,
  tooltipOpen,
  tooltipLeft,
  tooltipTop,
  tooltipData,
  hideTooltip,
  showTooltip,
  variant = 'Vertical',
  legend = true
}: BarStackProps & { height: number; width: number }): JSX.Element => {
  const { classes } = useBarStackStyles();

  const xMax = width - margin.left - margin.right;
  const yMax = height - margin.top - margin.bottom;

  xScale.rangeRound([0, xMax]);
  yScale.rangeRound([yMax, 0]);

  return (
    <div className={classes.container}>
      <div
        className={classes.svgContainer}
        style={{ height: height + 10, width: width + 10 }}
      >
        <svg height={height} width={width}>
          {/* <rect width={width} height={height} fill={"grey"} rx={14} /> */}
          <Group>
            <BarStackHorizontal
              color={colorScale}
              data={data}
              height={yMax}
              keys={keys}
              xScale={xScale}
              y={() => undefined}
              yScale={yScale}
            >
              {(barStacks) =>
                barStacks.map((barStack) =>
                  barStack.bars.map((bar) => (
                    <rect
                      fill={bar.color}
                      height={bar.height}
                      key={`barstack-horizontal-${barStack.index}-${bar.index}`}
                      width={bar.width}
                      x={bar.x}
                      y={bar.y}
                      onClick={() => {
                        console.log('clicked');
                      }}
                      onMouseLeave={() => {
                        tooltipTimeout = window.setTimeout(() => {
                          hideTooltip();
                        }, 300);
                      }}
                      onMouseMove={() => {
                        if (tooltipTimeout) clearTimeout(tooltipTimeout);
                        const top = bar.y + margin.top + bar.height;
                        const left = bar.x + bar.width;
                        showTooltip({
                          tooltipData: bar,
                          tooltipLeft: left,
                          tooltipTop: top
                        });
                      }}
                    />
                  ))
                )
              }
            </BarStackHorizontal>
          </Group>
        </svg>
      </div>

      {legend && (
        <div className={classes.legends}>
          <LegendOrdinal
            direction="row"
            labelMargin="0 15px 0 0"
            scale={legendScale}
          />
        </div>
      )}
      {tooltipOpen && tooltipData && (
        <Tooltip left={tooltipLeft} style={tooltipStyles} top={tooltipTop}>
          <div style={{ color: colorScale(tooltipData.key) }}>
            <strong>{tooltipData.key}</strong>
          </div>
          {/* <div>{tooltipData.bar.data[tooltipData.key]}℉</div>
          <div>
            <small>{formatDate(getDate(tooltipData.bar.data))}</small>
          </div> */}
        </Tooltip>
      )}
    </div>
  );
};

export default withTooltip(ResponsiveBarStack);
