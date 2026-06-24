import { describe, expect, it, rstest } from '@rstest/core';
import userEvent from '@testing-library/user-event';

import TimeInput from '../../www/front_src/src/Authentication/Local/TimeInputs/TimeInput';
import {
  labelMinute,
  labelMinutes
} from '../../www/front_src/src/Authentication/Local/translatedLabels';
import { renderApp, screen } from './render';

/**
 * Phase 0b port: a MUI SelectField (dropdown). Validates opening the dropdown
 * and picking an option (rendered in a portal) under Rstest jsdom.
 */
const baseProps = {
  inputLabel: 'input',
  labels: { plural: labelMinutes, singular: labelMinute },
  name: 'input',
  unit: 'minutes' as const
};

describe('TimeInput (Rstest app POC)', () => {
  it('updates the value to 2040000 ms when "34" is selected', async () => {
    const onChange = rstest.fn();
    renderApp(<TimeInput {...baseProps} onChange={onChange} timeValue={0} />);

    await userEvent.click(screen.getByLabelText(`input ${labelMinute}`));
    await userEvent.click(screen.getByText('34'));

    expect(onChange).toHaveBeenCalledWith(2040000);
  });

  it('does not show options below the configured min value except 0', async () => {
    renderApp(
      <TimeInput
        {...baseProps}
        minOption={2}
        onChange={rstest.fn()}
        timeValue={0}
      />
    );

    await userEvent.click(screen.getByLabelText(`input ${labelMinute}`));

    expect(screen.getAllByText('0')[0]).toBeInTheDocument();
    expect(screen.queryByText('1')).not.toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('uses the singular label when the value is 0', () => {
    renderApp(
      <TimeInput {...baseProps} onChange={rstest.fn()} timeValue={0} />
    );

    expect(screen.getByText(labelMinute)).toBeVisible();
  });

  it('uses the plural label when the value is 2 minutes', () => {
    renderApp(
      <TimeInput {...baseProps} onChange={rstest.fn()} timeValue={120000} />
    );

    expect(screen.getByText(labelMinutes)).toBeVisible();
  });
});
