import type { Group, InputProps } from '@centreon/ui';

interface FormInputsState {
  inputs: Array<InputProps>;
  groups: Array<Group>;
}

// Placeholder: ConfigurationBase requires a `form`.
const useFormInputs = (): FormInputsState => ({
  groups: [],
  inputs: []
});

export default useFormInputs;
