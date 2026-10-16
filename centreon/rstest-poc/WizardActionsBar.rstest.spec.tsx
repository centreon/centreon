import { describe, expect, it, rstest } from '@rstest/core';

import ActionsBar from '../packages/ui/src/Wizard/ActionsBar';
import { fireEvent, render, screen } from './testRender';

/** Port of packages/ui/src/Wizard/ActionsBar.test.tsx to Rstest. */
const goToPreviousStep = rstest.fn();
const goToNextStep = rstest.fn();
const submit = rstest.fn();

const actionsBarLabels = {
  labelFinish: 'Finish',
  labelNext: 'Next',
  labelPrevious: 'Previous'
};

describe('ActionsBar (Rstest POC)', () => {
  it('cannot finish if the form is not valid', () => {
    render(
      <ActionsBar
        actionsBarLabels={actionsBarLabels}
        disableActionButtons
        goToNextStep={goToNextStep}
        goToPreviousStep={goToPreviousStep}
        isFirstStep={false}
        isLastStep
        isSubmitting={false}
        submit={submit}
      />
    );

    expect(screen.getByLabelText('Finish')).toHaveAttribute('disabled');
  });

  it('displays custom previous/next labels when not on the last step', () => {
    render(
      <ActionsBar
        actionsBarLabels={{
          labelFinish: 'Custom finish',
          labelNext: 'Custom next',
          labelPrevious: 'Custom previous'
        }}
        disableActionButtons={false}
        goToNextStep={goToNextStep}
        goToPreviousStep={goToPreviousStep}
        isFirstStep={false}
        isLastStep={false}
        isSubmitting={false}
        submit={submit}
      />
    );

    expect(screen.getByText('Custom previous')).toBeInTheDocument();
    expect(screen.getByText('Custom next')).toBeInTheDocument();
  });

  it('displays custom previous/finish labels on the last step', () => {
    render(
      <ActionsBar
        actionsBarLabels={{
          labelFinish: 'Custom finish',
          labelNext: 'Custom next',
          labelPrevious: 'Custom previous'
        }}
        disableActionButtons
        goToNextStep={goToNextStep}
        goToPreviousStep={goToPreviousStep}
        isFirstStep={false}
        isLastStep
        isSubmitting={false}
        submit={submit}
      />
    );

    expect(screen.getByText('Custom previous')).toBeInTheDocument();
    expect(screen.getByText('Custom finish')).toBeInTheDocument();
  });

  it('goes to the previous step when "Previous" is clicked', () => {
    render(
      <ActionsBar
        actionsBarLabels={actionsBarLabels}
        disableActionButtons={false}
        goToNextStep={goToNextStep}
        goToPreviousStep={goToPreviousStep}
        isFirstStep={false}
        isLastStep={false}
        isSubmitting={false}
        submit={submit}
      />
    );

    fireEvent.click(screen.getByLabelText('Previous'));
    expect(goToPreviousStep).toHaveBeenCalled();
  });

  it('goes to the next step when "Next" is clicked', () => {
    render(
      <ActionsBar
        actionsBarLabels={actionsBarLabels}
        disableActionButtons={false}
        goToNextStep={goToNextStep}
        goToPreviousStep={goToPreviousStep}
        isFirstStep={false}
        isLastStep={false}
        isSubmitting={false}
        submit={submit}
      />
    );

    fireEvent.click(screen.getByLabelText('Next'));
    expect(goToNextStep).toHaveBeenCalled();
  });

  it('submits the wizard when "Finish" is clicked', () => {
    render(
      <ActionsBar
        actionsBarLabels={actionsBarLabels}
        disableActionButtons={false}
        goToNextStep={goToNextStep}
        goToPreviousStep={goToPreviousStep}
        isFirstStep={false}
        isLastStep
        isSubmitting={false}
        submit={submit}
      />
    );

    fireEvent.click(screen.getByLabelText('Finish'));
    expect(submit).toHaveBeenCalled();
  });
});
