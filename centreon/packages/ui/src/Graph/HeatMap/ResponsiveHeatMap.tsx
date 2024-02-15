import { useMemo } from 'react';

import { scaleLinear } from '@visx/scale';
import { T, equals, gt, lt } from 'ramda';

import { Box } from '@mui/material';

import { Tooltip } from '../../components';

import { HeatMapProps } from './model';
import { useHeatMapStyles } from './HeatMap.styles';

const gap = 8;
const maxTileSize = 120;
const smallestTileSize = 44;

const ResponsiveHeatMap = <TData,>({
  width,
  children,
  tiles,
  arrowClassName,
  tooltipContent,
  tileSizeFixed,
  displayTooltipCondition = T
}: HeatMapProps<TData> & { width: number }): JSX.Element | null => {
  const { classes, cx } = useHeatMapStyles();

  const tileSize = useMemo(() => {
    const scaleWidth = scaleLinear({
      clamp: true,
      domain: [680, 1056],
      range: [76, maxTileSize]
    });
    const tileWidth = scaleWidth(width);

    const tilesLength = tiles.length;

    const maxTotalTilesWidth =
      tilesLength * maxTileSize + (tilesLength - 1) * gap;
    const theoricalTotalTilesWidth =
      tilesLength * tileWidth + (tilesLength - 1) * gap;

    if (lt(width, 680) && gt(maxTotalTilesWidth, width) && !tileSizeFixed) {
      return smallestTileSize;
    }

    if (lt(theoricalTotalTilesWidth, width)) {
      return maxTileSize;
    }

    return tileSizeFixed ? maxTileSize : tileWidth;
  }, [width, tiles]);

  const isSmallestSize = equals(tileSize, smallestTileSize);

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
              {children({ backgroundColor, data, id, isSmallestSize })}
            </div>
          </Tooltip>
        </Box>
      ))}
    </Box>
  );
};

export default ResponsiveHeatMap;
