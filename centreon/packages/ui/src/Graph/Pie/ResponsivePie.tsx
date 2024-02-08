import { useState } from 'react';

import { Pie as PieComponent } from '@visx/shape';
import { Group } from '@visx/group';
import { Text } from '@visx/text';
import { LegendOrdinal } from '@visx/legend';
import { scaleOrdinal } from '@visx/scale';
import numeral from 'numeral';

import { PieProps } from './models';
import { usePieStyles } from './Pie.styles';

const ResponsivePie = ({
  title,
  variant = 'Pie',
  width,
  data,
  unit = 'Number',
  legend = true
}: PieProps & { width: number }): JSX.Element => {
  const { classes } = usePieStyles();
  const [active, setActive] = useState(null);
  const half = width / 2;

  const total = Math.floor(data.reduce((acc, { value }) => acc + value, 0));

  const formatValue = (value): string => {
    if (unit === 'Number') {
      return numeral(value).format('0a').toUpperCase();
    }

    return `${((value * 100) / total).toFixed(1)}%`;
  };

  const legendScale = scaleOrdinal({
    domain: data.map(({ value }) => formatValue(value)),
    range: data.map(({ color }) => color)
  });

  return (
    <div className={classes.container}>
      {variant === 'Pie' && title && (
        <div className={classes.pieTitle}>
          {`${numeral(total).format('0a').toUpperCase()} `} {title}
        </div>
      )}
      <div
        className={classes.svgContainer}
        style={{ height: width + 30, width: width + 30 }}
      >
        <svg height={width} width={width}>
          <Group left={half} top={half}>
            <PieComponent
              data={data}
              innerRadius={() => {
                const size = variant === 'Donut' ? 30 : half;

                return half - size;
              }}
              outerRadius={half}
              padAngle={0.01}
              pieValue={(items) => items.value}
            >
              {(pie) => {
                return pie.arcs.map((arc) => {
                  return (
                    <g
                      key={arc.data.label}
                      onMouseEnter={() => setActive(arc.data)}
                      onMouseLeave={() => setActive(null)}
                    >
                      <path d={pie.path(arc)} fill={arc.data.color} />
                    </g>
                  );
                });
              }}
            </PieComponent>
            {variant === 'Donut' && title && (
              <>
                <Text
                  dy={-15}
                  fill="#000"
                  fontSize={24}
                  fontWeight={700}
                  textAnchor="middle"
                >
                  {numeral(total).format('0a').toUpperCase()}
                </Text>
                <Text
                  dy={20}
                  fill="#000"
                  fontSize={24}
                  fontWeight={700}
                  textAnchor="middle"
                >
                  {title}
                </Text>
              </>
            )}
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
    </div>
  );
};

export default ResponsivePie;
