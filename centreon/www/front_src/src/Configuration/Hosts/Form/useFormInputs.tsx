import type { Group, InputProps } from '@centreon/ui';

interface FormInputsState {
  inputs: Array<InputProps>;
  groups: Array<Group>;
}

/**
 * Placeholder until the form is implemented.
 * `ConfigurationBase` requires a `form` prop, so the scaffold supplies an empty one.
 */
const useFormInputs = (): FormInputsState => ({
  groups: [],
  inputs: []
});

export default useFormInputs;
