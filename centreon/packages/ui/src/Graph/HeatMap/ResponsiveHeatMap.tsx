import { useMemo } from 'react';

import { scaleLinear } from '@visx/scale';
import { T, equals, lt } from 'ramda';

import { Box } from '@mui/material';

import { Tooltip } from '../../components';

import { useHeatMapStyles } from './HeatMap.styles';
import type { HeatMapProps } from './model';

const maxTileSize = 120;
const smallestTileSize = 44;
const median = 868;

const ResponsiveHeatMap = <TData,>({
  width,
  children,
  tiles,
  arrowClassName,
  tooltipContent,
  tileSizeFixed,
  displayTooltipCondition = T,
  height
}: HeatMapProps<TData> & {
  height: number;
  width: number;
}): JSX.Element | null => {
  const { classes, cx } = useHeatMapStyles();

  const tileSize = useMemo(() => {
    const scaleWidth = scaleLinear({
      clamp: true,
      domain: [680, 1056],
      range: [smallestTileSize, maxTileSize]
    });
    const tileWidth = scaleWidth(width);

    if (!tileSizeFixed) {
      if (lt(height, maxTileSize)) {
        return smallestTileSize;
      }
      return tileWidth;
    }
    return maxTileSize;
  }, [width, height, tileSizeFixed]);

  const isSmallestSize = equals(tileSize, smallestTileSize);
  const isSmallWrapper = lt(width, median);

  if (equals(width, 0)) {
    return null;
  }

  return (
    <Box
      sx={{
        display: 'grid',
        gap: 1,
        gridTemplateColumns: `repeat(auto-fit, ${tileSize}px)`
      }}
    >
      {tiles.map(({ backgroundColor, id, data }) => (
        <Box
          className={classes.heatMapTile}
          data-testid={id}
          key={id}
          sx={{ backgroundColor }}
        >
          <Tooltip
            hasCaret
            classes={{
              arrow: cx(classes.heatMapTooltipArrow, arrowClassName),
              tooltip: classes.heatMapTooltip
            }}
            data-testid={`tooltip-${data?.id}`}
            followCursor={false}
            label={
              displayTooltipCondition?.(data) &&
              tooltipContent?.({
                backgroundColor,
                data,
                id,
                isSmallestSize
              })
            }
            position="right-start"
          >
            <div className={classes.heatMapTileContent}>
              {children({
                backgroundColor,
                data,
                id,
                isSmallestSize,
                tileSize,
                isSmallWrapper
              })}
            </div>
          </Tooltip>
        </Box>
      ))}
    </Box>
  );
};

export default ResponsiveHeatMap;
