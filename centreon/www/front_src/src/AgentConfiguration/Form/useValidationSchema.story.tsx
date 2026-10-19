import { useEffect } from 'react';

import { useValidationSchema } from './useValidationSchema';

export interface ValidationResult {
  errors: Array<string>;
  valid: boolean;
}

declare global {
  interface Window {
    validateAgentConfiguration?: (
      values: unknown,
      context: Record<string, unknown>
    ) => Promise<ValidationResult>;
  }
}

// A hook story: exposes the schema validation to specs through window, since
// the Yup schema itself does not cross the Node/browser boundary.
export const Default = (): JSX.Element => {
  const schema = useValidationSchema();

  useEffect(() => {
    window.validateAgentConfiguration = async (values, context) => {
      try {
        await schema.validate(values, { abortEarly: false, context });

        return { errors: [], valid: true };
      } catch (error) {
        return {
          errors: (error as { errors: Array<string> }).errors,
          valid: false
        };
      }
    };
  }, [schema]);

  return <p>validation schema ready</p>;
};
