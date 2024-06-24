import numeral from 'numeral';

import PieChart from './PieChart';
import { ArcType, PieProps } from './models';

const defaultData = [
  { color: '#88B922', label: 'Ok', value: 148 },
  { color: '#999999', label: 'Unknown', value: 13 },
  { color: '#F7931A', label: 'Warning', value: 16 },
  { color: '#FF6666', label: 'Down', value: 62 }
];

const total = Math.floor(
  defaultData.reduce((acc, { value }) => acc + value, 0)
);

const TooltipContent = ({ label, color, value }: ArcType): JSX.Element => {
  return (
    <div data-testid={`tooltip-${label}`} style={{ color }}>
      {label} : {value}
    </div>
  );
};

const initialize = ({
  width = '500px',
  height = '500px',
  data = defaultData,
  ...args
}: Omit<PieProps, 'data'> & {
  data?;
  height?: string;
  width?: string;
}): void => {
  cy.mount({
    Component: (
      <div style={{ height, width }}>
        <PieChart {...args} data={data} />
      </div>
    )
  });
};

describe('Pie chart', () => {
  it('renders pie chart correctly with provided data', () => {
    initialize({});

    defaultData.forEach(({ label }) => {
      cy.findByTestId(label).should('be.visible');
    });

    cy.makeSnapshot();
  });

  it('adjusts size based on the provided width and height', () => {
    initialize({ displayLegend: false, height: '300px', width: '300px' });

    cy.findByTestId('pieChart')
      .should('have.css', 'width', '300px')
      .and('have.css', 'height', '300px');

    cy.makeSnapshot();
  });

  it('renders as a donut when variant is set to "donut"', () => {
    initialize({ variant: 'donut' });
    cy.get('[data-variant="donut"]').should('exist');

    cy.makeSnapshot();
  });

  it('renders as a pie when variant is set to "pie"', () => {
    initialize({ variant: 'pie' });
    cy.get('[data-variant="pie"]').should('exist');

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
  it('conditionally displays values on arcs based on displayValues prop', () => {
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

  it('displays values on arcs in percentage unit when displayValues is set to true and unit to percentage', () => {
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

  it('adjusts outer radius when chart dimensions are too small', () => {
    initialize({
      displayLegend: false,
      height: '120px',
      title: 'hosts',
      variant: 'donut',
      width: '120px'
    });

    cy.get('[data-variant="donut"]').should('have.css', 'width', '76px');

    cy.makeSnapshot();
  });
});
