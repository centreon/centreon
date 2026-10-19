import { describe, expect, it } from '@rstest/core';

import Wizard from '../packages/ui/src/Wizard';
import { fireEvent, render, screen, waitFor } from './testRender';

/**
 * Port of packages/ui/src/Wizard/index.test.tsx to Rstest.
 * A multi-step MUI component with async navigation (Next/Previous) — exercises
 * fireEvent + waitFor against the real component bundled by Rspack.
 */
const threeSteps = [
  {
    Component: (): JSX.Element => <div>Step 1</div>,
    skipFormChangeCheck: true,
    stepName: 'step label 1'
  },
  {
    Component: (): JSX.Element => <div>Step 2</div>,
    skipFormChangeCheck: true,
    stepName: 'step label 2'
  },
  {
    Component: (): JSX.Element => <div>Step 3</div>,
    skipFormChangeCheck: true,
    stepName: 'step label 3'
  }
];

describe('Wizard (Rstest POC)', () => {
  it('displays the step labels', () => {
    render(<Wizard open steps={threeSteps} />);

    expect(screen.getByText('step label 1')).toBeInTheDocument();
    expect(screen.getByText('step label 2')).toBeInTheDocument();
    expect(screen.getByText('step label 3')).toBeInTheDocument();
  });

  it('hides the step labels when there is only one step', () => {
    render(<Wizard open steps={[threeSteps[0]]} />);

    expect(screen.queryByText('step label 1')).not.toBeInTheDocument();
  });

  it('navigates between steps', async () => {
    render(<Wizard open steps={threeSteps} />);

    fireEvent.click(screen.getByText('Next'));
    await waitFor(() => {
      expect(screen.getByText('Step 2')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('Previous'));
    await waitFor(() => {
      expect(screen.getByText('Step 1')).toBeInTheDocument();
    });
  });
});
