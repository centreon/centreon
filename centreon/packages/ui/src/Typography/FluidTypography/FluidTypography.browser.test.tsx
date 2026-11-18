import { render } from '@test-setup/render';
import { screen } from '@testing-library/react';

import FluidTypography from '.';

interface InitializeProps {
  max?: string;
  min?: string;
  pref?: number;
  width: number;
}

// The viewport width is fixed, so the component gets a parent of each tested
// width: its font size depends on its container width only (`cqi` units).
const initialize = ({ width, min, max, pref }: InitializeProps): void => {
  render(
    <div style={{ width }}>
      <FluidTypography max={max} min={min} pref={pref} text="Unreachable" />
    </div>
  );
};

// clamp(min = 10px, pref = 19cqi, max = 1000px) with the one prop the test
// overrides, 1cqi being 1% of the container width.
const expectedFontSizes: Record<number, Record<string, string>> = {
  150: { max: '28.5px', min: '30px', pref: '15px' },
  300: { max: '57px', min: '57px', pref: '30px' },
  500: { max: '80px', min: '95px', pref: '50px' }
};

describe('FluidTypography', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  [500, 300, 150].forEach((width) => {
    Object.entries({ max: '80px', min: '30px', pref: 10 }).forEach(
      ([key, value]) => {
        it(`displays the text when the viewport is ${width}px and the ${key} is ${value}`, async () => {
          initialize({ [key]: value, width });

          const text = await screen.findByText('Unreachable');

          expect(text).toBeVisible();

          // The text size at that width.
          expect(getComputedStyle(text).fontSize).toBe(
            expectedFontSizes[width][key]
          );
        });
      }
    );
  });
});
