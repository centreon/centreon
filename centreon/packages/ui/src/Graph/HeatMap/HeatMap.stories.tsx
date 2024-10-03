import { Meta, StoryObj } from '@storybook/react';
import { makeStyles } from 'tss-react/mui';

import { Box, Typography } from '@mui/material';

import heatMapData from './HeatMapData.json';

import { HeatMap } from '.';

interface Data {
  counter: number;
  host: string;
  service: string;
}

const meta: Meta<typeof HeatMap<Data>> = {
  component: HeatMap
};

export default meta;
type Story = StoryObj<typeof HeatMap<Data>>;

const TooltipContent = ({ data }: { data: Data }): JSX.Element => {
  return (
    <Box sx={{ backgroundColor: 'common.white', color: 'common.black' }}>
      <Box
        sx={{
          backgroundColor: 'common.black',
          color: 'common.white',
          display: 'flex',
          justifyContent: 'center',
          py: 1,
          width: '100%'
        }}
      >
        <Typography>{data.host}</Typography>
      </Box>
      <Box sx={{ px: 1, textAlign: 'center' }}>
        <Typography>{data.service}</Typography>
        <Typography>{data.counter}</Typography>
      </Box>
    </Box>
  );
};

const useStyles = makeStyles()((theme) => ({
  arrow: {
    color: theme.palette.common.black
  }
}));

const Template = (args): JSX.Element => {
  const { classes } = useStyles();

  return <HeatMap {...args} arrowClassName={classes.arrow} />;
};

const TileContent = ({ data }: { data: Data }): JSX.Element => (
  <div
    style={{
      alignItems: 'center',
      display: 'flex',
      height: '100%',
      justifyContent: 'center'
    }}
  >
    {data.counter}
  </div>
);

export const normal: Story = {
  args: {
    children: TileContent,
    tiles: heatMapData
  }
};

export const withTooltip: Story = {
  args: {
    children: TileContent,
    tiles: heatMapData,
    tooltipContent: TooltipContent
  },
  render: Template
};

export const tilesWithFixedSize: Story = {
  args: {
    children: TileContent,
    tileSizeFixed: true,
    tiles: heatMapData
  }
};
