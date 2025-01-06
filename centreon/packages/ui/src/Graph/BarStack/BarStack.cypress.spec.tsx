import numeral from 'numeral';

import BarStack from './BarStack';
import { BarStackProps, BarType } from './models';

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

    cy.get('.visx-bar-rounded')
      .eq(0)
      .should(
        'have.attr',
        'd',
        'M8,95.18828451882847 h193 h8v8 v138.81171548117152 a8,8 0 0 1 -8,8 h-193 a8,8 0 0 1 -8,-8 v-138.81171548117152 v-8h8z'
      );

    cy.makeSnapshot();
  });

  it('renders as a horizontal bar when variant is set to "horizontal"', () => {
    initialize({ variant: 'horizontal' });

    cy.get('.visx-bar-rounded')
      .eq(0)
      .should(
        'have.attr',
        'd',
        'M8,0 h231.69874476987445 h8v8 v295 v8h-8 h-231.69874476987445 a8,8 0 0 1 -8,-8 v-295 a8,8 0 0 1 8,-8z'
      );
    cy.get('[data-is-vertical="false"]').should('be.visible');

    cy.makeSnapshot();
  });

  it('renders as a vertical bar when variant is set to "vertical"', () => {
    initialize({ variant: 'vertical' });

    cy.get('.visx-bar-rounded')
      .eq(0)
      .should(
        'have.attr',
        'd',
        'M8,133.26359832635984 h293 h8v8 v200.73640167364016 a8,8 0 0 1 -8,8 h-293 a8,8 0 0 1 -8,-8 v-200.73640167364016 v-8h8z'
      );
    cy.get('[data-is-vertical="true"]').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays tooltip with correct information on hover', () => {
    initialize({ TooltipContent });

    cy.get('.visx-bar-rounded')
      .eq(0)
      .should(
        'have.attr',
        'd',
        'M8,133.26359832635984 h293 h8v8 v200.73640167364016 a8,8 0 0 1 -8,8 h-293 a8,8 0 0 1 -8,-8 v-200.73640167364016 v-8h8z'
      );

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

    cy.get('.visx-bar-rounded')
      .eq(0)
      .should(
        'have.attr',
        'd',
        'M8,133.26359832635984 h293 h8v8 v200.73640167364016 a8,8 0 0 1 -8,8 h-293 a8,8 0 0 1 -8,-8 v-200.73640167364016 v-8h8z'
      );
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

    cy.get('.visx-bar-rounded')
      .eq(0)
      .should(
        'have.attr',
        'd',
        'M8,133.26359832635984 h293 h8v8 v200.73640167364016 a8,8 0 0 1 -8,8 h-293 a8,8 0 0 1 -8,-8 v-200.73640167364016 v-8h8z'
      );
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
    initialize({ displayLegend: false });

    cy.get('.visx-bar-rounded')
      .eq(0)
      .should(
        'have.attr',
        'd',
        'M8,133.26359832635984 h293 h8v8 v200.73640167364016 a8,8 0 0 1 -8,8 h-293 a8,8 0 0 1 -8,-8 v-200.73640167364016 v-8h8z'
      );
    cy.findByTestId('Ok').should('be.visible');
    cy.findByTestId('Legend').should('not.exist');

    cy.makeSnapshot();
  });

  it('displays the title when the title is given', () => {
    initialize({ title: 'host' });

    cy.get('.visx-bar-rounded')
      .eq(0)
      .should(
        'have.attr',
        'd',
        'M8,133.26359832635984 h293 h8v8 v200.73640167364016 a8,8 0 0 1 -8,8 h-293 a8,8 0 0 1 -8,-8 v-200.73640167364016 v-8h8z'
      );
    cy.findByTestId('Ok').should('be.visible');
    cy.findByTestId('Title').should('be.visible');

    cy.makeSnapshot();
  });

  it('displays the bars within a small display', () => {
    initialize({
      width: '120px',
      height: '89px',
      title: 'host',
      displayLegend: true
    });

    cy.get('.visx-bar-rounded')
      .eq(0)
      .should(
        'have.attr',
        'd',
        'M8,20.941422594142264 h94 h8v8 v18.058577405857733 a8,8 0 0 1 -8,8 h-94 a8,8 0 0 1 -8,-8 v-18.058577405857733 v-8h8z'
      );
    cy.findByTestId('Ok').should('be.visible');

    cy.makeSnapshot();
  });
});
