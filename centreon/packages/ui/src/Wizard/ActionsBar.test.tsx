import { render, screen, fireEvent } from '../testRenderer';

import ActionsBar from './ActionsBar';

const goToPreviousStep = jest.fn();
const goToNextStep = jest.fn();
const submit = jest.fn();

const actionsBarLabels = {
  labelFinish: 'Finish',
  labelNext: 'Next',
  labelPrevious: 'Previous'
};

describe('ActionsBar', () => {
  it('cannot finish if the form is not valid', () => {
    render(
      <ActionsBar
        disableActionButtons
        isLastStep
        actionsBarLabels={actionsBarLabels}
        goToNextStep={goToNextStep}
        goToPreviousStep={goToPreviousStep}
        isFirstStep={false}
        isSubmitting={false}
        submit={submit}
      />
    );

    expect(screen.getByLabelText('Finish')).toHaveAttribute('disabled');
  });

  it('displays the given previous and next labels when the current page is not the last one', () => {
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

  it('displays the given previous and finish labels when the current page is the last one', () => {
    render(
      <ActionsBar
        disableActionButtons
        isLastStep
        actionsBarLabels={{
          labelFinish: 'Custom finish',
          labelNext: 'Custom next',
          labelPrevious: 'Custom previous'
        }}
        goToNextStep={goToNextStep}
        goToPreviousStep={goToPreviousStep}
        isFirstStep={false}
        isSubmitting={false}
        submit={submit}
      />
    );

    expect(screen.getByText('Custom previous')).toBeInTheDocument();
    expect(screen.getByText('Custom finish')).toBeInTheDocument();
  });

  it('goes to previous step when the "Previous" button is clicked', () => {
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

  it('goes to next step when the "Next" button is clicked', () => {
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

  it('submits the wizard when the "Finish" button is clicked', () => {
    render(
      <ActionsBar
        isLastStep
        actionsBarLabels={actionsBarLabels}
        disableActionButtons={false}
        goToNextStep={goToNextStep}
        goToPreviousStep={goToPreviousStep}
        isFirstStep={false}
        isSubmitting={false}
        submit={submit}
      />
    );

    fireEvent.click(screen.getByLabelText('Finish'));
    expect(submit).toHaveBeenCalled();
  });
});
