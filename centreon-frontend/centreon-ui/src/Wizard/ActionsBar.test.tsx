import * as React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import ActionsBar from './ActionsBar';

const goToPreviousStep = jest.fn();
const goToNextStep = jest.fn();
const submit = jest.fn();

const actionsBarLabels = {
  labelFinish: 'Finish',
  labelNext: 'Next',
  labelPrevious: 'Previous',
};

describe('ActionsBar', () => {
  it('cannot finish if the form is not valid', () => {
    render(
      <ActionsBar
        isLastStep
        isFirstStep={false}
        goToPreviousStep={goToPreviousStep}
        goToNextStep={goToNextStep}
        isSubmitting={false}
        submit={submit}
        actionsBarLabels={actionsBarLabels}
        disableActionButtons
      />,
    );

    expect(screen.getByLabelText('Finish')).toHaveAttribute('disabled');
  });

  it('displays the given previous and next labels when the current page is not the last one', () => {
    render(
      <ActionsBar
        isLastStep={false}
        isFirstStep={false}
        goToPreviousStep={goToPreviousStep}
        goToNextStep={goToNextStep}
        isSubmitting={false}
        submit={submit}
        actionsBarLabels={{
          labelFinish: 'Custom finish',
          labelNext: 'Custom next',
          labelPrevious: 'Custom previous',
        }}
        disableActionButtons={false}
      />,
    );

    expect(screen.getByText('Custom previous')).toBeInTheDocument();
    expect(screen.getByText('Custom next')).toBeInTheDocument();
  });

  it('displays the given previous and finish labels when the current page is the last one', () => {
    render(
      <ActionsBar
        isLastStep
        isFirstStep={false}
        goToPreviousStep={goToPreviousStep}
        goToNextStep={goToNextStep}
        isSubmitting={false}
        submit={submit}
        actionsBarLabels={{
          labelFinish: 'Custom finish',
          labelNext: 'Custom next',
          labelPrevious: 'Custom previous',
        }}
        disableActionButtons
      />,
    );

    expect(screen.getByText('Custom previous')).toBeInTheDocument();
    expect(screen.getByText('Custom finish')).toBeInTheDocument();
  });

  it('goes to previous step when the "Previous" button is clicked', () => {
    render(
      <ActionsBar
        isLastStep={false}
        isFirstStep={false}
        goToPreviousStep={goToPreviousStep}
        goToNextStep={goToNextStep}
        isSubmitting={false}
        submit={submit}
        actionsBarLabels={actionsBarLabels}
        disableActionButtons={false}
      />,
    );

    fireEvent.click(screen.getByLabelText('Previous'));
    expect(goToPreviousStep).toHaveBeenCalled();
  });

  it('goes to next step when the "Next" button is clicked', () => {
    render(
      <ActionsBar
        isLastStep={false}
        isFirstStep={false}
        goToPreviousStep={goToPreviousStep}
        goToNextStep={goToNextStep}
        isSubmitting={false}
        submit={submit}
        actionsBarLabels={actionsBarLabels}
        disableActionButtons={false}
      />,
    );

    fireEvent.click(screen.getByLabelText('Next'));
    expect(goToNextStep).toHaveBeenCalled();
  });

  it('submits the wizard when the "Finish" button is clicked', () => {
    render(
      <ActionsBar
        isLastStep
        isFirstStep={false}
        goToPreviousStep={goToPreviousStep}
        goToNextStep={goToNextStep}
        isSubmitting={false}
        submit={submit}
        actionsBarLabels={actionsBarLabels}
        disableActionButtons={false}
      />,
    );

    fireEvent.click(screen.getByLabelText('Finish'));
    expect(submit).toHaveBeenCalled();
  });
});
