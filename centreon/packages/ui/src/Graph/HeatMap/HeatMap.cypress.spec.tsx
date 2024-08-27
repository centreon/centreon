import { pluck } from 'ramda';

import { Box, Typography } from '@mui/material';

import EllipsisTypography from '../../Typography/EllipsisTypography';

import HeatMap from './HeatMap';
import heatMapData from './HeatMapData.json';
import { HeatMapProps } from './model';

const dataIds = pluck('id', heatMapData);

interface Data {
  counter: number;
  host: string;
  service: string;
}

const TileContent = ({
  isSmallestSize,
  data
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

const initialize = ({
  width = '100vw',
  height = '100vh',
  ...args
}: Omit<HeatMapProps<Data>, 'tiles' | 'children'> & {
  height?: string;
  width?: string;
}): void => {
  cy.mount({
    Component: (
      <div style={{ height, width }}>
        <HeatMap tiles={heatMapData} {...args}>
          {TileContent}
        </HeatMap>
      </div>
    )
  });
};

describe('HeatMap', () => {
  it('displays tiles', () => {
    initialize({});
    dataIds.forEach((id) => {
      cy.findByTestId(id).should('be.visible');
    });
  });

  it('does not display the tooltip when the prop is not set and the tile is hovered', () => {
    initialize({});

    cy.findByTestId(dataIds[0]).trigger('mouseover');

    cy.findByTestId(`tooltip-${dataIds[0]}`).should('not.exist');
  });

  it('displays the tooltip when the prop is set and the tile is hovered', () => {
    initialize({
      tooltipContent: ({ data }) => (
        <Typography>
          This is the tooltip for {data.host}-{data.service}-{data.counter}
        </Typography>
      )
    });

    cy.findByTestId(dataIds[0]).trigger('mouseover');

    cy.contains('This is the tooltip for Server-Service Counter-53').should(
      'be.visible'
    );
  });

  it('displays the tooltip conditionally when the prop is set and the tile is hovered', () => {
    initialize({
      displayTooltipCondition: ({ counter }) => counter > 100,
      tooltipContent: ({ data }) => (
        <Typography>
          This is the tooltip for {data.host}-{data.service}-{data.counter}
        </Typography>
      )
    });

    cy.findByTestId(dataIds[0]).trigger('mouseover');

    cy.contains('This is the tooltip for Server-Service Counter-53').should(
      'not.exist'
    );

    cy.findByTestId(dataIds[1]).trigger('mouseover');

    cy.contains('This is the tooltip for Server-Service Counter-779').should(
      'be.visible'
    );
  });

  it('displays tiles with fixed size', () => {
    initialize({ height: '200px', tileSizeFixed: true, width: '590px' });
    dataIds.forEach((id) => {
      cy.findByTestId(id).should('be.visible');
    });
  });

  it('displays tiles as small when the container width is under the breakpoint', () => {
    initialize({ height: '200px', width: '590px' });
    dataIds.forEach((id) => {
      cy.findByTestId(id).should('be.visible');
    });

    cy.makeSnapshot();
  });

  it('does not display tiles as small when the contains width is 0', () => {
    initialize({ height: '200px', width: '0px' });
    dataIds.forEach((id) => {
      cy.findByTestId(id).should('not.exist');
    });

    cy.makeSnapshot();
  });

  it('displays a single tile', () => {
    initialize({ tiles: [heatMapData[0]] });
    cy.findByTestId(dataIds[0]).should('be.visible');
  });
});
