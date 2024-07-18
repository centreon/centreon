import numeral from 'numeral';

import BarStack from './BarStack';
import { BarType, BarStackProps } from './models';

const defaultData = [
  { color: '#88B922', label: 'Ok', value: 148 },
  { color: '#999999', label: 'Unknown', value: 13 },
  { color: '#F7931A', label: 'Warning', value: 16 },
  { color: '#FF6666', label: 'Down', value: 62 }
];

const total = Math.floor(
  defaultData.reduce((acc, { value }) => acc + value, 0)
);

const TooltipContent = ({ label, color, value }: BarType): JSX.Element => {
  return (
    <div data-testid={`tooltip-${label}`} style={{ color }}>
      {label} : {value}
    </div>
  );
};

const initialize = ({
  width = '400px',
  height = '400px',
  data = defaultData,
  ...args
}: Omit<BarStackProps, 'data'> & {
  data?;
  height?: string;
  width?: string;
}): void => {
  cy.mount({
    Component: (
      <div style={{ height, width }}>
        <BarStack {...args} data={data} />
      </div>
    )
  });
};

describe('Bar stack', () => {
  it('renders Bar stack correctly with provided data', () => {
    initialize({});

    defaultData.forEach(({ label }) => {
      cy.findByTestId(label).should('be.visible');
    });

    cy.makeSnapshot();
  });

  it('adjusts size based on the provided width and height', () => {
    initialize({ displayLegend: false, height: '300px', width: '300px' });

    cy.findByTestId('barStack').should('have.css', 'height', '270px');

    cy.makeSnapshot();
  });

  it('renders as a horizontal bar when variant is set to "horizontal"', () => {
    initialize({ variant: 'horizontal' });
    cy.get('[data-variant="horizontal"]').should('exist');

    cy.makeSnapshot();
  });

  it('renders as a vertical bar when variant is set to "vertical"', () => {
    initialize({ variant: 'vertical' });
    cy.get('[data-variant="vertical"]').should('exist');

    cy.makeSnapshot();
  });

  it('displays tooltip with correct information on hover', () => {
    initialize({ TooltipContent });

    defaultData.forEach(({ label, value }) => {
      cy.findByTestId(label).trigger('mouseover', { force: true });

      cy.findByTestId(`tooltip-${label}`)
        .should('contain', label)
        .and('contain', numeral(value).format('0a').toUpperCase());
    });

    cy.makeSnapshot();
  });

  it('conditionally displays values on rects based on displayValues prop', () => {
    initialize({ displayValues: true });
    defaultData.forEach(({ value }, index) => {
      cy.findAllByTestId('value')
        .eq(index)
        .children()
        .eq(0)
        .should('have.text', value);
    });

    initialize({ displayValues: false });
    cy.findAllByTestId('value').should('not.exist');

    cy.makeSnapshot();
  });

  it('displays values on rects in percentage unit when displayValues is set to true and unit to percentage', () => {
    initialize({ displayValues: true, unit: 'percentage' });
    defaultData.forEach(({ value }, index) => {
      cy.findAllByTestId('value')
        .eq(index)
        .children()
        .eq(0)
        .should('have.text', `${((value * 100) / total).toFixed(1)}%`);
    });

    cy.makeSnapshot();
  });

  it('displays Legend component based on displayLegend prop', () => {
    initialize({ displayLegend: true });
    cy.findByTestId('Legend').should('be.visible');

    initialize({ displayLegend: false });
    cy.findByTestId('Legend').should('not.exist');

    cy.makeSnapshot();
  });

  it('displays the title when the title is giving', () => {
    initialize({ title: 'host' });
    cy.findByTestId('Title').should('be.visible');

    initialize({});
    cy.findByTestId('Title').should('not.exist');

    cy.makeSnapshot();
  });
});
