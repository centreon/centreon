import React from 'react';

import { BarStack } from '@visx/shape';
import { Group } from '@visx/group';
import { scaleBand, scaleLinear, scaleOrdinal } from '@visx/scale';
import { useTooltip, useTooltipInPortal, defaultStyles } from '@visx/tooltip';
import { LegendOrdinal } from '@visx/legend';
import { localPoint } from '@visx/event';

type TooltipData = {
  bar;
  color: string;
  height: number;
  index: number;
  key: string;
  width: number;
  x: number;
  y: number;
};

export type BarStackProps = {
  events?: boolean;
  height: number;
  margin?: { bottom: number; left: number; right: number; top: number };
  width: number;
};

export const background = '#eaedff';
const defaultMargin = { bottom: 0, left: 0, right: 0, top: 0 };
const tooltipStyles = {
  ...defaultStyles,
  backgroundColor: 'rgba(0,0,0,0.9)',
  color: 'white',
  minWidth: 60
};

const data = [{ Down: 22, Ok: 121, Unknown: 19, Warning: 13 }];
const keys = Object.keys(data[0]);

const total = Object.values(data[0]).reduce((acc, curr) => acc + curr, 0);

// scales
const yScale = scaleLinear({
  domain: [0, total],
  nice: true
});
const xScale = scaleBand({
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

let tooltipTimeout: number;

export default ({
  width,
  height,
  events = false,
  margin = defaultMargin
}: BarStackProps) => {
  const {
    tooltipOpen,
    tooltipLeft,
    tooltipTop,
    tooltipData,
    hideTooltip,
    showTooltip
  } = useTooltip<TooltipData>();

  const { containerRef, TooltipInPortal } = useTooltipInPortal({
    // TooltipInPortal is rendered in a separate child of <body /> and positioned
    // with page coordinates which should be updated on scroll. consider using
    // Tooltip or TooltipWithBounds if you don't need to render inside a Portal
    scroll: true
  });

  if (width < 10) return null;
  // bounds
  const xMax = width;
  const yMax = height - margin.top - 100;

  xScale.rangeRound([0, xMax]);
  yScale.range([yMax, 0]);

  return width < 10 ? null : (
    <div style={{ position: 'relative' }}>
      <svg height={height} ref={containerRef} width={width}>
        <Group top={margin.top}>
          <BarStack
            color={colorScale}
            data={data}
            keys={keys}
            x={() => undefined}
            xScale={xScale}
            yScale={yScale}
          >
            {(barStacks) =>
              barStacks.map((barStack) =>
                barStack.bars.map((bar) => (
                  <rect
                    fill={bar.color}
                    height={bar.height}
                    key={`bar-stack-${barStack.index}-${bar.index}`}
                    width={bar.width}
                    x={bar.x}
                    y={bar.y}
                    onClick={() => {
                      if (events) alert(`clicked: ${JSON.stringify(bar)}`);
                    }}
                    onMouseLeave={() => {
                      tooltipTimeout = window.setTimeout(() => {
                        hideTooltip();
                      }, 300);
                    }}
                    onMouseMove={(event) => {
                      if (tooltipTimeout) clearTimeout(tooltipTimeout);
                      // TooltipInPortal expects coordinates to be relative to containerRef
                      // localPoint returns coordinates relative to the nearest SVG, which
                      // is what containerRef is set to in this example.
                      const eventSvgCoords = localPoint(event);
                      const left = bar.x + bar.width / 2;
                      showTooltip({
                        tooltipData: bar,
                        tooltipLeft: left,
                        tooltipTop: eventSvgCoords?.y
                      });
                    }}
                  />
                ))
              )
            }
          </BarStack>
        </Group>
      </svg>
      <div
        style={{
          display: 'flex',
          fontSize: '14px',
          justifyContent: 'center',
          position: 'absolute',
          top: margin.top / 2 - 10,
          width: '100%'
        }}
      >
        <LegendOrdinal
          direction="row"
          labelMargin="0 15px 0 0"
          scale={legendScale}
        />
      </div>

      {tooltipOpen && tooltipData && (
        <TooltipInPortal
          left={tooltipLeft}
          style={tooltipStyles}
          top={tooltipTop}
        >
          <div style={{ color: colorScale(tooltipData.key) }}>
            <strong>{tooltipData.key}</strong>
          </div>
          <div>{tooltipData.bar.data[tooltipData.key]}℉</div>
          <div />
        </TooltipInPortal>
      )}
    </div>
  );
};
