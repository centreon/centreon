import { Box, Typography } from '@mui/material';

import EllipsisTypography from '../../Typography/EllipsisTypography';
import HeatMap from './HeatMap';
import heatMapData from './HeatMapData.json';

interface Data {
  counter: number;
  host: string;
  service: string;
}

interface Props {
  height?: string;
  singleTile?: boolean;
  tileSizeFixed?: boolean;
  /** No tooltip, a tooltip on every tile, or above 100 only. */
  tooltip?: 'all' | 'none' | 'over100';
  width?: string;
}

const TileContent = ({
  data,
  isSmallestSize
}: {
  data: Data;
  isSmallestSize: boolean;
}): JSX.Element | false =>
  !isSmallestSize && (
    <Box
      sx={{
        alignItems: 'center',
        color: 'common.black',
        display: 'flex',
        flexDirection: 'column',
        width: '100%'
      }}
    >
      <EllipsisTypography textAlign="center">{data.host}</EllipsisTypography>
      <EllipsisTypography textAlign="center">{data.service}</EllipsisTypography>
      <EllipsisTypography textAlign="center">{data.counter}</EllipsisTypography>
    </Box>
  );

const tooltipContent = ({ data }: { data: Data }): JSX.Element => (
  <Typography>
    This is the tooltip for {data.host}-{data.service}-{data.counter}
  </Typography>
);

export const Default = ({
  height = '100vh',
  singleTile = false,
  tileSizeFixed,
  tooltip = 'none',
  width = '100vw'
}: Props): JSX.Element => (
  <div style={{ height, width }}>
    <HeatMap
      displayTooltipCondition={
        tooltip === 'over100' ? ({ counter }) => counter > 100 : undefined
      }
      tileSizeFixed={tileSizeFixed}
      tiles={singleTile ? [heatMapData[0]] : heatMapData}
      tooltipContent={tooltip === 'none' ? undefined : tooltipContent}
    >
      {TileContent}
    </HeatMap>
  </div>
);
